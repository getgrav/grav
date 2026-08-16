<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;

/**
 * Regression tests for getgrav/grav#4023: Page::url($canonical = true) must
 * return an absolute `routes.canonical` value verbatim instead of
 * concatenating it onto the site's root URL.
 */
class PageUrlCanonicalTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Pages $pages */
    protected $pages;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->grav['config']->set('system.home.alias', '/home');

        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->pages->init();
    }

    public function testCanonicalUrlWithAbsoluteRouteIsReturnedVerbatim(): void
    {
        $page = $this->pages->find('/about');

        $page->routeCanonical('https://www.example.tld/about');

        self::assertSame('https://www.example.tld/about', $page->url(false, true));
        self::assertSame('https://www.example.tld/about', $page->url(true, true));
    }

    public function testCanonicalUrlWithRelativeRouteIsStillPrefixedWithRootUrl(): void
    {
        $page = $this->pages->find('/about');

        $page->routeCanonical('/about-canonical');

        $rootUrl = $this->grav['uri']->rootUrl(false) . $this->pages->baseRoute();

        self::assertSame($rootUrl . '/about-canonical', $page->url(false, true));
    }

    public function testCanonicalUrlDefaultsToRegularRouteWhenUnset(): void
    {
        $page = $this->pages->find('/about');

        self::assertSame($page->url(false, false), $page->url(false, true));
    }
}
