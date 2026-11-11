<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * A page with `cascade_unpublish` unpublishes every page below it while it is unpublished itself, by its
 * `published` setting or by its dates. The pages below keep their own `published` value, so they are back
 * as they were once the parent is published again. `system.pages.cascade_unpublish` sets the default.
 */
class PagesCascadeUnpublishTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $root;

    /** @var string */
    protected $pagesDir;

    /** @var array<string,mixed> */
    protected $configBefore = [];

    /** @var string[] */
    protected $scanFilesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        foreach (['system.cache.enabled', 'system.pages.lazy_index', 'system.pages.publish_dates', 'system.pages.cascade_unpublish', 'system.home.alias'] as $key) {
            $this->configBefore[$key] = $config->get($key);
        }
        $config->set('system.cache.enabled', true);
        $config->set('system.cache.check.method', 'file');
        $config->set('system.cache.check.interval', 0);
        $config->set('system.pages.publish_dates', true);
        $config->set('system.pages.cascade_unpublish', false);
        $config->set('system.pages.lazy_index', false);
        $config->set('system.languages.supported', []);
        $config->set('system.home.alias', '/home');
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();

        $cache = new class($this->grav) extends Cache {
            /** @var array<string,mixed> */
            public $store = [];

            public function fetch($id)
            {
                return $this->store[$id] ?? false;
            }

            public function save($id, $data, $lifetime = null)
            {
                $this->store[$id] = $data;
            }

            public function fetchShared($id)
            {
                return $this->fetch($id);
            }

            public function saveShared($id, $data, $lifetime = null)
            {
                $this->save($id, $data, $lifetime);
            }

            public function delete($id)
            {
                unset($this->store[$id]);

                return true;
            }
        };
        unset($this->grav['cache']);
        $this->grav['cache'] = $cache;

        $this->root = sys_get_temp_dir() . '/grav-cascade-unpublish-' . bin2hex(random_bytes(4));
        $this->pagesDir = $this->root . '/pages';

        $this->writePage('01.home/default.md', ['title' => 'Home']);

        // Unpublished and cascading: everything below is unpublished, even a page that says published: true.
        $this->writePage('02.section/default.md', ['title' => 'Section', 'published' => false, 'cascade_unpublish' => true]);
        $this->writePage('02.section/01.child/default.md', ['title' => 'Child', 'published' => true]);
        $this->writePage('02.section/01.child/grandchild/default.md', ['title' => 'Grandchild']);
        $this->writePage('02.section/02.folder/03.in-folder/default.md', ['title' => 'In folder']);

        // Unpublished without cascading: the pages below stay published, as before.
        $this->writePage('03.plain/default.md', ['title' => 'Plain', 'published' => false]);
        $this->writePage('03.plain/01.child/default.md', ['title' => 'Plain child']);

        // Scheduled and cascading: the pages below wait for the parent's publish date.
        $this->writePage('04.scheduled/default.md', ['title' => 'Scheduled', 'publish_date' => '2099-01-01 00:00', 'cascade_unpublish' => true]);
        $this->writePage('04.scheduled/01.child/default.md', ['title' => 'Scheduled child']);

        // Expired and cascading.
        $this->writePage('05.expired/default.md', ['title' => 'Expired', 'unpublish_date' => '2020-01-01 00:00', 'cascade_unpublish' => true]);
        $this->writePage('05.expired/01.child/default.md', ['title' => 'Expired child']);

        // Published and cascading: nothing happens while the parent is published.
        $this->writePage('06.live/default.md', ['title' => 'Live', 'cascade_unpublish' => true]);
        $this->writePage('06.live/01.inner/default.md', ['title' => 'Inner', 'published' => false]);
        $this->writePage('06.live/01.inner/01.leaf/default.md', ['title' => 'Leaf']);

        // Opts out while the site default cascades.
        $this->writePage('07.opt-out/default.md', ['title' => 'Opt out', 'published' => false, 'cascade_unpublish' => false]);
        $this->writePage('07.opt-out/01.child/default.md', ['title' => 'Opt out child']);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', $this->pagesDir, false);

        $this->scanFilesBefore = glob($this->scanDir() . '/*') ?: [];
    }

    protected function tearDown(): void
    {
        foreach ($this->configBefore as $key => $value) {
            $this->grav['config']->set($key, $value);
        }

        if ($this->root) {
            foreach (array_diff(glob($this->scanDir() . '/*') ?: [], $this->scanFilesBefore) as $file) {
                @unlink($file);
            }
            $this->removeDir($this->root);
        }

        parent::tearDown();
    }

    public function testCascadingParentUnpublishesEveryPageBelowIt(): void
    {
        $pages = $this->request();

        self::assertFalse($pages->find('/section', true)->published());
        foreach (['/section/child', '/section/child/grandchild', '/section/folder/in-folder'] as $route) {
            $page = $pages->find($route, true);
            self::assertInstanceOf(PageInterface::class, $page, $route);
            self::assertFalse($page->published(), $route);
            self::assertFalse($page->routable(), $route);
            self::assertFalse($page->visible(), $route);
            self::assertSame('/section', $page->unpublishedBy(), $route);
            // PagesServiceProvider answers a dispatched page that is not routable with a 404.
            $dispatched = $pages->dispatch($route, false, false);
            self::assertTrue(null === $dispatched || !$dispatched->routable(), $route . ' is not served');
        }
    }

    /**
     * Without the cascade, an unpublished page that is asked for redirects to its first published child.
     * With it, there is no published child to send the visitor to.
     */
    public function testUnpublishedSectionDoesNotRedirectToAChild(): void
    {
        $pages = $this->request();

        $dispatched = $pages->dispatch('/section', false, false);
        self::assertTrue(null === $dispatched || !$dispatched->routable());
        self::assertCount(0, $pages->find('/section', true)->children()->visible()->routable()->published());
        self::assertCount(1, $pages->find('/plain', true)->children()->visible()->routable()->published());
    }

    public function testChildKeepsItsOwnSetting(): void
    {
        $child = $this->request()->find('/section/child', true);

        self::assertTrue($child->header()->published, 'The header is left alone');
    }

    public function testParentWithoutCascadeLeavesChildrenPublished(): void
    {
        $pages = $this->request();

        self::assertFalse($pages->find('/plain', true)->published());
        $child = $pages->find('/plain/child');
        self::assertNotNull($child);
        self::assertTrue($child->published());
        self::assertNull($child->unpublishedBy());
    }

    public function testDatesOnTheParentCascade(): void
    {
        $pages = $this->request();

        self::assertFalse($pages->find('/scheduled/child', true)->published(), 'Scheduled parent');
        self::assertSame('/scheduled', $pages->find('/scheduled/child', true)->unpublishedBy());
        self::assertFalse($pages->find('/expired/child', true)->published(), 'Expired parent');
    }

    public function testPublishedParentChangesNothing(): void
    {
        $pages = $this->request();

        self::assertTrue($pages->find('/live')->published());
        self::assertFalse($pages->find('/live/inner', true)->published());
        self::assertNull($pages->find('/live/inner', true)->unpublishedBy(), 'Unpublished by its own setting');
        self::assertTrue($pages->find('/live/inner/leaf')->published(), 'Inner does not cascade');
    }

    public function testSiteDefaultAndPageOverride(): void
    {
        $this->grav['config']->set('system.pages.cascade_unpublish', true);
        $pages = $this->request();

        self::assertFalse($pages->find('/plain/child', true)->published(), 'The site default cascades');
        self::assertSame('/plain', $pages->find('/plain/child', true)->unpublishedBy());
        self::assertTrue($pages->find('/opt-out/child')->published(), 'cascade_unpublish: false on the page wins');
    }

    public function testCollectionsLeaveOutPagesBelowAnUnpublishedParent(): void
    {
        $pages = $this->request();

        $children = $pages->find('/section', true)->children();
        self::assertCount(2, $children);
        self::assertCount(0, $children->published());
    }

    public function testLazyIndexKeepsTheCascade(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The lazy page index needs pdo_sqlite.');
        }

        $this->grav['config']->set('system.pages.lazy_index', true);
        $this->request();
        $pages = $this->request();
        self::assertSame('lazy', $pages->getIndexStats()['mode']);

        $grandchild = $pages->find('/section/child/grandchild', true);
        self::assertFalse($grandchild->published());
        self::assertSame('/section', $grandchild->unpublishedBy());
        self::assertTrue($pages->find('/plain/child')->published());
    }

    public function testSetterKeepsTheCascade(): void
    {
        $child = $this->request()->find('/section/child', true);
        self::assertInstanceOf(Page::class, $child);

        self::assertFalse($child->published(true), 'Publishing the child itself does not undo the parent');
        $child->unpublishedBy(null);
        self::assertTrue($child->published());
    }

    /**
     * Simulate a new request: a fresh Pages service over the same cache.
     */
    private function request(): Pages
    {
        $pages = new Pages($this->grav);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        return $pages;
    }

    private function writePage(string $path, array $header): void
    {
        $file = $this->pagesDir . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $yaml = \Grav\Common\Yaml::dump($header);
        file_put_contents($file, "---\n{$yaml}---\n\n{$header['title']} body\n");
        touch($file, time() - 3600);
        touch(dirname($file), time() - 3600);
        clearstatcache();
    }

    private function scanDir(): string
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $dir = (string)$locator->findResource('cache://compiled/pages', true, true);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
