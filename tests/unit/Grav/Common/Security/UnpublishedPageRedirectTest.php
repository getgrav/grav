<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class UnpublishedPageRedirectTest
 *
 * A page's `redirect` header is followed only while the page is published, so
 * an unpublished, scheduled or expired page gives the same 404 with or without
 * the header. The canonical-route redirect in PagesServiceProvider
 * (`system.pages.redirect_default_route`) is only done for routable pages.
 *
 * In CLI Grav::close() throws instead of sending the redirect, which is what
 * these tests look for.
 */
class UnpublishedPageRedirectTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.languages.supported', []);
        $this->grav['config']->set('system.home.alias', '/item1');
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();
        $this->grav['uri']->initializeWithUrl('http://localhost/item2')->init();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();
    }

    public function testPublishedPageRedirects(): void
    {
        $this->page('/item2')->redirect('https://example.com/target');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('redirect to https://example.com/target');
        $this->grav['pages']->dispatch('/item2');
    }

    public function testNonRoutablePageStillRedirects(): void
    {
        // A folder that only forwards is a supported construction.
        $page = $this->page('/item2');
        $page->redirect('https://example.com/target');
        $page->routable(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('redirect to https://example.com/target');
        $this->grav['pages']->dispatch('/item2');
    }

    public function testUnpublishedPageDoesNotRedirect(): void
    {
        $page = $this->page('/item2/item2-1/item2-1-1');
        $page->redirect('https://example.com/target');
        $page->published(false);

        $found = $this->grav['pages']->dispatch('/item2/item2-1/item2-1-1');
        self::assertFalse($found && $found->routable());
    }

    public function testUnpublishedByDateDoesNotRedirect(): void
    {
        $page = $this->page('/item2/item2-1/item2-1-2');
        $page->redirect('https://example.com/target');
        // A future publish_date switches published off in init() (system.pages.publish_dates).
        $page->publishDate(date('Y-m-d H:i', time() + 86400));
        $page->init(new SplFileInfo((string)$page->filePath()));
        self::assertFalse($page->published());

        $found = $this->grav['pages']->dispatch('/item2/item2-1/item2-1-2');
        self::assertFalse($found && $found->routable());
    }

    public function testDefaultRouteRedirectNeedsRoutablePage(): void
    {
        $this->grav['config']->set('system.pages.redirect_default_route', 302);
        $this->grav['uri']->initializeWithUrl('http://localhost/item2/item2-1/item2-1-1.html')->init();
        $this->page('/item2/item2-1/item2-1-1')->published(false);

        // Resolves the request through PagesServiceProvider: a 404 page, no redirect.
        self::assertFalse($this->grav['page']->routable());
    }

    protected function page(string $route): PageInterface
    {
        $page = $this->grav['pages']->find($route, true);
        self::assertInstanceOf(PageInterface::class, $page, "Fake page {$route} not found");

        return $page;
    }
}
