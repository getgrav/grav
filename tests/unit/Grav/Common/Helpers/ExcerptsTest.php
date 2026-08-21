<?php

use Codeception\Util\Fixtures;
use Grav\Common\Helpers\Excerpts;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Uri;
use Grav\Common\Config\Config;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Media;
use Grav\Common\Language\Language;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ExcerptsTest
 */
class ExcerptsTest extends \PHPUnit\Framework\TestCase
{
    /** @var Parsedown $parsedown */
    protected $parsedown;

    /** @var Grav $grav */
    protected $grav;

    /** @var PageInterface $page */
    protected $page;

    /** @var Pages $pages */
    protected $pages;

    /** @var Config $config */
    protected $config;

    /** @var  Uri $uri */
    protected $uri;

    /** @var  Language $language */
    protected $language;

    protected $old_home;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->config = $this->grav['config'];
        $this->uri = $this->grav['uri'];
        $this->language = $this->grav['language'];
        $this->old_home = $this->config->get('system.home.alias');
        $this->config->set('system.home.alias', '/item1');
        $this->config->set('system.absolute_urls', false);
        $this->config->set('system.languages.supported', []);

        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->pages->init();

        $defaults = [
            'extra'            => false,
            'auto_line_breaks' => false,
            'auto_url_links'   => false,
            'escape_markup'    => false,
            'special_chars'    => ['>' => 'gt', '<' => 'lt'],
        ];
        $this->page = $this->pages->find('/item2/item2-2');
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();
    }

    protected function tearDown(): void
    {
        $this->config->set('system.home.alias', $this->old_home);
    }


    public function testProcessImageHtml(): void
    {
        self::assertMatchesRegularExpression(
            '|<img alt="Sample Image" src="\/images\/.*-sample-image.jpe?g\" data-src="sample-image\.jpg\?cropZoom=300,300" \/>|',
            Excerpts::processImageHtml('<img src="sample-image.jpg?cropZoom=300,300" alt="Sample Image" />', $this->page)
        );
        self::assertMatchesRegularExpression(
            '|<img alt="Sample Image" class="foo" src="\/images\/.*-sample-image.jpe?g\" data-src="sample-image\.jpg\?classes=foo" \/>|',
            Excerpts::processImageHtml('<img src="sample-image.jpg?classes=foo" alt="Sample Image" />', $this->page)
        );
    }

    public function testNoProcess(): void
    {
        self::assertStringStartsWith(
            '<a href="https://play.google.com/store/apps/details?hl=de" id="org.jitsi.meet" target="_blank"',
            Excerpts::processLinkHtml('<a href="https://play.google.com/store/apps/details?id=org.jitsi.meet&hl=de&target=_blank">regular process</a>')
        );

        self::assertStringStartsWith(
            '<a href="https://play.google.com/store/apps/details?id=org.jitsi.meet&hl=de&target=_blank"',
            Excerpts::processLinkHtml('<a href="https://play.google.com/store/apps/details?id=org.jitsi.meet&hl=de&target=_blank&noprocess">noprocess</a>')
        );

        self::assertStringStartsWith(
            '<a href="https://play.google.com/store/apps/details?id=org.jitsi.meet&hl=de" target="_blank"',
            Excerpts::processLinkHtml('<a href="https://play.google.com/store/apps/details?id=org.jitsi.meet&hl=de&target=_blank&noprocess=id">noprocess=id</a>')
        );
    }

    public function testTarget(): void
    {
        self::assertStringStartsWith(
            '<a href="https://play.google.com/store/apps/details" target="_blank"',
            Excerpts::processLinkHtml('<a href="https://play.google.com/store/apps/details?target=_blank">only target</a>')
        );
        self::assertStringStartsWith(
            '<a href="https://meet.weikamp.biz/Support" rel="nofollow" target="_blank"',
            Excerpts::processLinkHtml('<a href="https://meet.weikamp.biz/Support?rel=nofollow&target=_blank">target and rel</a>')
        );
    }

    public function testImageFilenameWithColonIsResolvedAsLocalMedia(): void
    {
        // getgrav/grav#3933: a bare relative filename containing a literal ':'
        // (e.g. a timestamp) must still resolve to the page's own media, not be
        // misread by parse_url() as a scheme:path split.
        //
        // The fixture is generated here at runtime rather than committed to
        // git: ':' is a reserved character on NTFS, so a statically committed
        // file with this name would make `git clone`/checkout of the whole
        // repo fail on Windows. Page media loads lazily on first access, so
        // it's enough for the file to exist before processImageHtml() runs.
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('A colon is not a legal filename character on Windows.');
        }

        $fixturePath = GRAV_ROOT . '/tests/fake/nested-site/user/pages/02.item2/02.item2-2/2025-06-29T13:36:56.png';
        $image = imagecreatetruecolor(10, 10);
        imagepng($image, $fixturePath);
        imagedestroy($image);

        // The page's media collection was already built (by Pages::init())
        // before this fixture existed on disk, so force a fresh scan of the
        // folder now that the file is there.
        $this->page->media(new Media($this->page->getMediaFolder(), $this->page->getMediaOrder()));

        try {
            self::assertMatchesRegularExpression(
                '|<img alt="Timestamped" src="\/images\/.*-2025-06-29t133656\.png" data-src="2025-06-29T13:36:56\.png\?cropZoom=300,300" \/>|',
                Excerpts::processImageHtml('<img src="2025-06-29T13:36:56.png?cropZoom=300,300" alt="Timestamped" />', $this->page)
            );
        } finally {
            @unlink($fixturePath);
        }
    }

    public function testMailtoAndTelLinksAreNotBrokenByColonFix(): void
    {
        // Non-regression: any scheme without "://" that still has valid,
        // letter-led URI scheme grammar (RFC 3986) must pass through
        // untouched, whether or not Grav has special-case handling for it.
        self::assertStringStartsWith(
            '<a href="mailto:bob@example.com"',
            Excerpts::processLinkHtml('<a href="mailto:bob@example.com">email</a>')
        );
        self::assertStringStartsWith(
            '<a href="tel:+123456789"',
            Excerpts::processLinkHtml('<a href="tel:+123456789">call</a>')
        );
        self::assertStringStartsWith(
            '<a href="xmpp:xyx@domain.com"',
            Excerpts::processLinkHtml('<a href="xmpp:xyx@domain.com">xmpp</a>')
        );
    }

    public function testStreamImageIsNotBrokenByColonFix(): void
    {
        // Non-regression: registered Grav streams (image://, user://, ...) must
        // keep resolving via the locator exactly as before.
        self::assertStringStartsWith(
            '<img alt="Stream" src="/system/images/watermark.png"',
            Excerpts::processImageHtml('<img src="image://watermark.png" alt="Stream" />', $this->page)
        );
    }

    /**
     * @dataProvider letterLedColonFilenameProvider
     */
    public function testLetterLedColonFilenameIsTreatedAsLocalMedia(string $filename): void
    {
        // RFC 3986 scheme grammar alone cannot separate "note:2025.png" from an
        // unknown protocol - both are a letter-led token plus a colon - so these
        // are recognised by carrying a media extension instead. Without that the
        // fix would only cover colon filenames whose leading token happens to
        // start with a digit (getgrav/grav#3933).
        $result = Excerpts::processImageHtml('<img src="' . $filename . '" alt="Colon" />', $this->page);

        // Resolved against the page's own route rather than passed through as a
        // scheme, which is what happened before: the src was left verbatim.
        self::assertStringContainsString('src="/item2/item2-2/' . $filename . '"', $result);
    }

    public function letterLedColonFilenameProvider(): array
    {
        return [
            'word prefix' => ['note:2025.png'],
            'uppercase prefix' => ['IMG:001.png'],
            'multiple colons' => ['a:b:c.png'],
            'no separator' => ['Screenshot2025at13:36:56.png'],
        ];
    }

    public function testMediaExtensionArmDoesNotCaptureRealSchemes(): void
    {
        // The media-extension check must never reinterpret a genuine protocol as
        // a local file, however the reference happens to end. Grav's own external
        // scheme list plus data: are excluded outright.
        foreach (['mailto:someone@example.com', 'tel:+123456789', 'git:example.com/repo.png'] as $href) {
            self::assertStringStartsWith(
                '<a href="' . $href . '"',
                Excerpts::processLinkHtml('<a href="' . $href . '">link</a>'),
                $href . ' must pass through untouched'
            );
        }
    }
}
