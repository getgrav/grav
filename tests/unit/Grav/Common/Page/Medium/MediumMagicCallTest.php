<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Covers MediaObjectTrait::__call(), the catch-all that turns an unrecognized
 * method name into a querystring suffix on the medium's URL.
 *
 * getgrav/grav#4301: Twig resolves `{{ image.copyright }}` for a key that is
 * absent from the `.meta.yaml` sidecar by falling through to __call(), which
 * rewrote the image `src` to `<url>?copyright`. An unknown zero-argument name
 * must now read as an absent property, while the documented media actions and
 * every argument-carrying call keep working.
 */
class MediumMagicCallTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var Media */
    protected $media;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->media = new Media(GRAV_ROOT . '/tests/fake/media-metadata');
    }

    private function render(string $template, $medium): string
    {
        $twig = new Environment(new ArrayLoader([]));

        return $twig->createTemplate($template)->render(['image' => $medium]);
    }

    public function testAbsentMetaKeyRendersEmptyAndLeavesTheUrlAlone(): void
    {
        // photo4.jpg has no `.meta.yaml` sidecar at all.
        $medium = $this->media['photo4.jpg'];

        $this->assertSame('', $this->render('{{ image.copyright }}', $medium));
        $this->assertStringNotContainsString('copyright', $medium->url());
        $this->assertStringNotContainsString('?', $medium->url());
    }

    public function testAbsentMetaKeyMatchesBracketNotation(): void
    {
        $medium = $this->media['photo4.jpg'];

        $this->assertSame(
            $this->render("{{ image['copyright'] }}", $medium),
            $this->render('{{ image.copyright }}', $medium)
        );
    }

    public function testPresentMetaKeyStillResolves(): void
    {
        // photo1.jpg carries copyright: Jane Doe in its sidecar.
        $medium = $this->media['photo1.jpg'];

        $this->assertSame('Jane Doe', $this->render('{{ image.copyright }}', $medium));
        $this->assertStringNotContainsString('?', $medium->url());
    }

    public function testUnknownZeroArgumentCallReturnsNull(): void
    {
        $medium = $this->media['photo4.jpg'];

        $this->assertNull($medium->copyright());
        $this->assertStringNotContainsString('copyright', $medium->url());
    }

    public function testArgumentsStillBuildTheQuerystring(): void
    {
        // Cache-busting and third-party params passed from Markdown or Twig.
        $medium = $this->media['photo4.jpg'];
        $medium->v('2');

        $this->assertStringContainsString('v=2', $medium->url());
    }

    public function testMarkdownFlagStyleParamStillPassesThrough(): void
    {
        // Excerpts::processMediaActions() calls `![](img.png?myflag)` as
        // `$medium->myflag('')` — a single empty argument, not zero arguments.
        $medium = $this->media['photo4.jpg'];
        $medium->myflag('');

        $this->assertStringContainsString('myflag', $medium->url());
    }

    public function testDocumentedActionStillAppendsOnANonImageMedium(): void
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/tests/fake/nested-site/user/pages/02.item2/02.item2-2/existing-file.zip');
        $this->assertNotNull($medium);
        $this->assertNotInstanceOf(ImageMedium::class, $medium);

        $medium->resize();

        $this->assertStringContainsString('resize', $medium->url());
    }

    public function testImageActionChainIsUnaffected(): void
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg');
        $this->assertInstanceOf(ImageMedium::class, $medium);

        $url = $medium->cropZoom(100, 100)->url();

        $this->assertStringContainsString('/images/', $url);
        $this->assertStringNotContainsString('?cropZoom', $url);
    }

    public function testQuerystringKeepsEachParamOnceInOrder(): void
    {
        $medium = $this->media['photo4.jpg'];

        $this->assertSame('?a=1&b', $medium->querystring('?a=1&b'));
        $this->assertSame('?a=1&b&c', $medium->querystring('&b&c&a=1'));
        $this->assertSame('a=1&b&c&7', $medium->querystring('7', false));

        $medium->foo('bar', 'b z')->foo('bar', 'b z')->b('');
        $this->assertSame('a=1&b&c&7&foo=bar,b%20z', $medium->querystring(null, false));
    }

    public function testQuerystringParamsReachTheAlternatives(): void
    {
        $medium = $this->media['photo4.jpg'];
        $alternative = $this->media['photo3.jpg'];
        $medium->addAlternative(2, $alternative);

        $medium->querystring('a=1');
        $medium->v('2');

        $this->assertSame('?a=1&v=2', $medium->querystring());
        $this->assertSame('?a=1&v=2', $alternative->querystring());
    }

    public function testQuerystringParamsReachDerivativesMadeLater(): void
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg');
        $this->assertInstanceOf(ImageMedium::class, $medium);

        // `![](img.jpg?foo&derivatives=320,640&bar)`: both params belong to every
        // candidate of the srcset, whichever side of `derivatives` they are on.
        $medium->foo('')->derivatives([320, 640])->bar('');

        $alternatives = $medium->getAlternatives();
        $this->assertCount(3, $alternatives);
        foreach ($alternatives as $width => $alternative) {
            $this->assertSame('?foo&bar', $alternative->querystring(), "{$width}w");
        }
    }

    public function testManyUrlParamsCostLinearTime(): void
    {
        // Each param is stored once as it comes in, instead of the whole querystring
        // being split and de-duplicated again for every pass-through param.
        $query = implode('&', array_map(static fn($number) => 'p' . $number, range(1, 1000)));
        $excerpts = new Excerpts(null, ['markdown' => [], 'images' => []]);

        $start = microtime(true);
        $medium = $excerpts->processMediaActions($this->media['photo4.jpg'], 'photo4.jpg?' . $query);

        $this->assertLessThan(1.0, microtime(true) - $start);
        $this->assertSame('?' . $query, $medium->querystring());
    }
}
