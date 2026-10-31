<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class PagesServiceProviderTest
 *
 * Covers the `system.force_ssl` redirect performed by the `page` container
 * service, including on the 404/notfound fallback path (#3703), and that the
 * service resolves the request to the page `Pages::find(Uri::path())` returns.
 */
class PagesServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Config */
    protected $config;

    /** @var Uri */
    protected $uri;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $this->grav = $grav();
        $this->config = $this->grav['config'];
        $this->uri = $this->grav['uri'];

        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $pages->init();
    }

    protected function tearDown(): void
    {
        $this->config->set('system.force_ssl', false);
        $this->uri->initializeWithUrl('http://localhost/')->init();

        parent::tearDown();
    }

    public function testForceSslRedirectsOnExistingPage(): void
    {
        $this->config->set('system.force_ssl', true);
        $this->uri->initializeWithUrl('http://testing.dev/blog')->init();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#redirect to https://testing\.dev/blog#');

        $this->grav['page'];
    }

    public function testForceSslRedirectsOnNotFoundPage(): void
    {
        $this->config->set('system.force_ssl', true);
        $this->uri->initializeWithUrl('http://testing.dev/this-page-does-not-exist')->init();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#redirect to https://testing\.dev/this-page-does-not-exist#');

        $this->grav['page'];
    }

    public function testNoRedirectWhenForceSslDisabledOnNotFoundPage(): void
    {
        $this->config->set('system.force_ssl', false);
        $this->uri->initializeWithUrl('http://testing.dev/this-page-does-not-exist')->init();

        $page = $this->grav['page'];

        self::assertFalse($page->routable());
    }

    public function testEncodedPathIsDecodedOnce(): void
    {
        $this->uri->initializeWithUrl('http://testing.dev/en/%62log')->init();

        self::assertSame('/blog', $this->grav['page']->route());
    }

    public function testEncodedPathIsNotDecodedTwice(): void
    {
        $this->uri->initializeWithUrl('http://testing.dev/en/%2562log')->init();

        self::assertNull($this->grav['pages']->find($this->uri->path(), true));
        self::assertFalse($this->grav['page']->routable());
    }

    /**
     * Plugins apply a page's `access` rules to its media by looking the page up
     * from `Uri::path()`, so it has to be the page the file is served from.
     */
    public function testMediaFallbackIsNotDecodedTwice(): void
    {
        $this->uri->initializeWithUrl('http://testing.dev/en/%2562log/image.jpg')->init();

        $fallback = false;
        $listener = static function (Event $event) use (&$fallback): void {
            $fallback = $event['page'];
        };
        $this->grav['events']->addListener('onPageFallBackUrl', $listener);
        try {
            $this->grav['page'];
        } finally {
            $this->grav['events']->removeListener('onPageFallBackUrl', $listener);
        }

        self::assertNull($this->grav['pages']->find(dirname($this->uri->path()), true));
        self::assertNull($fallback);
    }
}
