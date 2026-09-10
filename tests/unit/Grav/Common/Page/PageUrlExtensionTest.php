<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class PageUrlExtensionTest
 *
 * Page::url() with an output format, and `/index.<ext>` as the home page's
 * address in that format. `/.rss`, `/.json` and `/.md` were the only way to
 * ask for the home page in another format, and a leading dot is a hidden file
 * to every web server.
 */
class PageUrlExtensionTest extends \PHPUnit\Framework\TestCase
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
        $this->grav['uri']->initializeWithUrl('http://localhost/')->init();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();
    }

    public function testUrlWithAnExtension(): void
    {
        $page = $this->page('/item2');

        self::assertSame('/item2', $page->url());
        self::assertSame('/item2.md', $page->url(false, false, true, false, 'md'));
        self::assertSame('/item2.rss', $page->url(false, false, true, false, '.rss'));
        self::assertSame('http://localhost/item2.json', $page->url(true, false, true, false, 'json'));
    }

    public function testHomePageWithAnExtensionIsIndex(): void
    {
        $home = $this->page('/');

        self::assertTrue($home->home());
        self::assertSame('/', $home->url());
        self::assertSame('/index.md', $home->url(false, false, true, false, 'md'));
        self::assertSame('http://localhost/index.rss', $home->url(true, false, true, false, 'rss'));
        // The raw route already names a real folder, so it carries the extension itself.
        self::assertSame('/item1.md', $home->url(false, false, true, true, 'md'));
    }

    public function testIndexWithAFormatExtensionDispatchesToHome(): void
    {
        $pages = $this->grav['pages'];

        $this->grav['uri']->initializeWithUrl('http://localhost/index.md')->init();
        $page = $pages->dispatch('/index');
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertTrue($page->home());

        // Without a format there is no such page.
        $this->grav['uri']->initializeWithUrl('http://localhost/index')->init();
        self::assertNull($pages->dispatch('/index'));

        // An unknown extension is not a format request either.
        $this->grav['uri']->initializeWithUrl('http://localhost/index.zip')->init();
        self::assertNull($pages->dispatch('/index'));
    }

    protected function page(string $route): PageInterface
    {
        $page = $this->grav['pages']->find($route);
        self::assertInstanceOf(PageInterface::class, $page, "Fake page {$route} not found");

        return $page;
    }
}
