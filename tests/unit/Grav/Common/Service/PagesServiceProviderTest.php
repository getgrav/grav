<?php

use Codeception\Util\Fixtures;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class PagesServiceProviderTest
 *
 * Covers the `system.force_ssl` redirect performed by the `page` container
 * service, including on the 404/notfound fallback path (#3703).
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
}
