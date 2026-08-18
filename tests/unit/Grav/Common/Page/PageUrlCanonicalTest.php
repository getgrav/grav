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

    /**
     * @dataProvider absoluteCanonicalProvider
     */
    public function testCanonicalUrlWithVariousAbsoluteShapesIsReturnedVerbatim(string $canonical): void
    {
        $page = $this->pages->find('/about');

        $page->routeCanonical($canonical);

        self::assertSame($canonical, $page->url(false, true));
    }

    public function absoluteCanonicalProvider(): array
    {
        return [
            'https with path' => ['https://www.example.tld/about'],
            'https origin only' => ['https://www.my-url.tld'],
            'protocol relative' => ['//www.example.tld/about'],
            'query and fragment preserved' => ['https://www.my-url.tld/a?b=c#frag'],
        ];
    }

    public function testCanonicalUrlWithArrayRouteDoesNotCrash(): void
    {
        // A malformed `routes.canonical` header can hand back an array. The
        // is_string() guard in url() is the one line stopping Uri::isExternal()
        // from being handed an array and throwing a TypeError, so assert the
        // concrete result rather than merely that nothing fatal happened —
        // assertIsString() alone passes even when a warning is raised.
        $page = $this->pages->find('/about');

        $page->header()->routes = ['canonical' => ['/some-route']];

        // Falls back to the page's own route rather than fataling.
        self::assertSame($page->url(false, false), @$page->url(false, true));
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
