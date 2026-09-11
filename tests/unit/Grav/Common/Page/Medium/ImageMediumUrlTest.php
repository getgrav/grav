<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * Covers the `url` override that ImageMedium honors for unmodified originals
 * (used by the Flex Object media proxy). A queued image operation must ignore
 * the override and keep serving from the image cache.
 */
class ImageMediumUrlTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $image = 'tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    private function medium(): ImageMedium
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/' . $this->image);
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    public function testPlainUrlIsUnchangedWithoutOverride(): void
    {
        // Regression: a normal image still resolves to its on-disk path.
        $url = $this->medium()->url();
        $this->assertStringContainsString('home-sample-image.jpg', $url);
        $this->assertStringNotContainsString('/flex-media/', $url);
    }

    public function testOverrideIsReturnedForUnmodifiedOriginal(): void
    {
        $medium = $this->medium();
        $medium->set('url', '/flex-media/contacts/0001/home-sample-image.jpg');

        $this->assertSame('/flex-media/contacts/0001/home-sample-image.jpg', $medium->url());
    }

    public function testOverrideIsIgnoredOnDerivative(): void
    {
        $medium = $this->medium();
        $medium->set('url', '/flex-media/contacts/0001/home-sample-image.jpg');

        // A queued operation produces a cached derivative under images/ — the
        // override must NOT apply here.
        $url = $medium->cropResize(50, 50)->url();
        $this->assertStringNotContainsString('/flex-media/', $url);
        $this->assertStringContainsString('/images/', $url);
    }

    public function testIncludeHostPrependsTheAbsoluteBase(): void
    {
        // #894: url(true, true) mirrors page.url(true) and gives a full URL for one
        // media item without turning on absolute_urls for the whole site.
        $absolute = (string)$this->grav['base_url_absolute'];
        $this->assertMatchesRegularExpression('#^https?://#', $absolute);

        $relative = $this->medium()->url();
        $this->assertStringStartsNotWith('http', $relative);

        $full = $this->medium()->url(true, true);
        $this->assertStringStartsWith($absolute . '/', $full);
        $this->assertStringEndsWith($relative, $full);

        // A resized derivative gets the host too.
        $derivative = $this->medium()->cropResize(50, 50)->url(true, true);
        $this->assertStringStartsWith($absolute . '/', $derivative);
        $this->assertStringContainsString('/images/', $derivative);
    }

    public function testIncludeHostPrefixesARootRelativeOverride(): void
    {
        // The override from `pages.media_route_urls` is the page route, which
        // already carries the base path, so only the scheme and host are added.
        $override = '/flex-media/contacts/0001/home-sample-image.jpg';
        $medium = $this->medium();
        $medium->set('url', $override);

        $host = (string)$this->grav['uri']->base();
        $this->assertMatchesRegularExpression('#^https?://[^/]+$#', $host);
        $this->assertSame($host . $override, $medium->url(true, true));
        $this->assertSame($override, $medium->url());
    }

    public function testIncludeHostLeavesAnAbsoluteOverrideAlone(): void
    {
        $medium = $this->medium();
        foreach (['https://cdn.example.com/home-sample-image.jpg', '//cdn.example.com/home-sample-image.jpg'] as $override) {
            $medium->set('url', $override);
            $this->assertSame($override, $medium->url(true, true));
        }
    }

    public function testIncludeHostPrefixesTheOverrideOfANonImageFile(): void
    {
        $medium = MediumFactory::fromFile(GRAV_ROOT . '/tests/fake/nested-site/user/pages/02.item2/02.item2-2/existing-file.zip');
        $this->assertNotNull($medium);
        $this->assertNotInstanceOf(ImageMedium::class, $medium);
        $medium->set('url', '/item2/item2-2/existing-file.zip');

        $this->assertSame((string)$this->grav['uri']->base() . '/item2/item2-2/existing-file.zip', $medium->url(true, true));
    }
}
