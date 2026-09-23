<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\PageIndexStore;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * With system.pages.lazy_index the pages load from a per-page SQLite index instead of one cache
 * blob. These tests pin that a lazily loaded site answers exactly like the blob one, that
 * collections and full walks load their pages and children lists in batches instead of one query
 * per page, and that 'auto' only turns the index on for sites above the page threshold.
 */
class PagesLazyIndexTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $root;

    /** @var string */
    protected $pagesDir;

    /** @var string[] */
    protected $scanFilesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The lazy page index needs pdo_sqlite.');
        }

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $config->set('system.cache.enabled', true);
        $config->set('system.cache.check.method', 'file');
        $config->set('system.cache.check.interval', 0);
        $config->set('system.languages.supported', []);
        $config->set('system.home.alias', '/home');
        $config->set('site.taxonomies', ['category', 'tag']);
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

            public function delete($id)
            {
                unset($this->store[$id]);

                return true;
            }
        };
        unset($this->grav['cache']);
        $this->grav['cache'] = $cache;

        $this->root = sys_get_temp_dir() . '/grav-lazy-index-' . bin2hex(random_bytes(4));
        $this->pagesDir = $this->root . '/pages';
        $this->writePage('01.home/default.md', ['title' => 'Home']);
        $this->writePage('02.blog/blog.md', ['title' => 'Blog', 'content' => ['items' => '@self.children', 'order' => ['by' => 'date', 'dir' => 'desc']]]);
        foreach (range(1, 12) as $i) {
            $this->writePage(sprintf('02.blog/post-%02d/item.md', $i), [
                'title' => sprintf('Post %02d', $i),
                'date' => sprintf('2026-01-%02d', 13 - $i),
                'taxonomy' => ['category' => ['blog'], 'tag' => $i % 2 ? ['odd'] : ['even']],
            ]);
        }
        $this->writePage('02.blog/post-03/gallery/default.md', ['title' => 'Gallery', 'taxonomy' => ['category' => ['blog']]]);
        $this->writePage('02.blog/draft/item.md', ['title' => 'Draft', 'published' => false, 'taxonomy' => ['category' => ['blog']]]);
        $this->writePage('03.landing/modular.md', ['title' => 'Landing']);
        $this->writePage('03.landing/_hero/hero.md', ['title' => 'Hero']);
        $this->writePage('03.landing/_features/features.md', ['title' => 'Features']);
        $this->writePage('04.about/default.md', ['title' => 'About', 'slug' => 'about-us']);
        $this->writePage('04.about/team/default.md', ['title' => 'Team']);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', $this->pagesDir, false);

        $this->scanFilesBefore = glob($this->scanDir() . '/*') ?: [];
    }

    protected function tearDown(): void
    {
        if ($this->root) {
            foreach (array_diff(glob($this->scanDir() . '/*') ?: [], $this->scanFilesBefore) as $file) {
                @unlink($file);
            }
            $this->removeDir($this->root);
        }

        parent::tearDown();
    }

    public function testLazyRequestAnswersLikeTheBlob(): void
    {
        $blob = $this->snapshot($this->request(false));
        $this->request(false);

        $this->grav['cache']->store = [];
        $this->request(true);
        $pages = $this->request(true);
        self::assertSame('lazy', $pages->getIndexStats()['mode'], 'The second lazy request reads the index store');

        self::assertSame($blob, $this->snapshot($pages));
    }

    public function testIteratingAllPagesLoadsThemInBatches(): void
    {
        $this->request(true);
        $pages = $this->countingRequest();

        $count = 0;
        foreach ($pages->all() as $page) {
            self::assertInstanceOf(PageInterface::class, $page);
            $count++;
        }

        self::assertSame(21, $count);
        self::assertSame(0, $pages->singleLoads, 'No page was loaded with its own query');
        self::assertGreaterThan(0, $pages->batches);
    }

    public function testAllLoadsChildrenListsALevelAtATime(): void
    {
        $this->request(true);
        $pages = $this->countingRequest();

        $pages->all();

        self::assertSame(0, $pages->childListReads, 'No children list was read with its own query');
        self::assertSame(1, $pages->getIndexStats()['hydrated'], 'Walking the tree loaded no pages beyond the root');
    }

    public function testDescendantsAreFilteredWithoutLoadingEveryPage(): void
    {
        $this->request(false);
        $expected = $this->descendants($this->request(false));

        $this->grav['cache']->store = [];
        $this->request(true);
        $pages = $this->request(true);

        $collection = $pages->getCollection(['items' => ['@page.descendants' => '/blog'], 'order' => ['by' => 'date', 'dir' => 'desc'], 'limit' => 3]);

        // At most the root and /blog itself: the published and module filters and the date
        // sort used the flags and dates stored in the children index.
        self::assertLessThanOrEqual(2, $pages->getIndexStats()['hydrated']);
        self::assertSame(array_slice($expected, 0, 3), $this->titles($collection));
    }

    public function testDescendantItemsKeepTheirSlug(): void
    {
        $pages = $this->request(false);
        $collection = $pages->getCollection(['items' => ['@page.descendants' => '/about']]);

        self::assertSame(['slug' => 'team'], array_intersect_key(current($collection->toArray()), ['slug' => true]));
    }

    public function testTaxonomyCollectionMatchesTheBlob(): void
    {
        $this->request(false);
        $expected = $this->titles($this->grav['taxonomy']->findTaxonomy(['tag' => 'odd']));

        $this->grav['cache']->store = [];
        $this->request(true);
        $pages = $this->countingRequest();

        self::assertSame($expected, $this->titles($this->grav['taxonomy']->findTaxonomy(['tag' => 'odd'])));
        self::assertSame(0, $pages->singleLoads);
    }

    public function testLoadedPageStaysTheSameObject(): void
    {
        $this->request(true);
        $pages = $this->request(true);

        $post = $pages->find('/blog/post-05');
        self::assertInstanceOf(PageInterface::class, $post);
        $post->title('Changed at runtime');

        $titles = $this->titles($pages->find('/blog')->children());
        self::assertContains('Changed at runtime', $titles, 'Prefetching never replaces a page that is already loaded');
    }

    public function testAutoUsesTheIndexOnlyFromTheThreshold(): void
    {
        $this->grav['config']->set('system.pages.lazy_index', 'auto');

        $small = fn() => new class($this->grav) extends Pages {
            protected const LAZY_INDEX_AUTO_PAGES = 5;
        };

        // The fixture has 21 pages: above a threshold of 5, below the shipped one.
        self::assertSame('lazy', $this->request(null, $small())->getIndexStats()['mode']);
        self::assertSame('lazy', $this->request(null, $small())->getIndexStats()['mode']);

        $this->grav['cache']->store = [];
        self::assertSame('blob', $this->request(null)->getIndexStats()['mode']);
        self::assertSame('blob', $this->request(null)->getIndexStats()['mode']);
    }

    public function testDisabledIndexIgnoresAStoreFromAnEarlierRequest(): void
    {
        $this->request(true);
        $this->request(true);

        // A cache written with the index on, read with it off: the pages are rebuilt into the blob.
        self::assertSame('blob', $this->request(false)->getIndexStats()['mode']);
        self::assertSame('blob', $this->request(false)->getIndexStats()['mode']);
    }

    public function testStoreReadsManyRowsAcrossBatches(): void
    {
        $store = PageIndexStore::open($this->root . '/store', 'test');
        self::assertNotNull($store);

        $pages = [];
        $children = [];
        foreach (range(0, 1199) as $i) {
            $pages["/p/{$i}"] = "payload-{$i}";
            $children["/p/{$i}"] = $i % 3 ? [] : ["/p/{$i}/c" => ['slug' => "c{$i}"]];
        }
        self::assertTrue($store->rebuild('id', ['pages' => $pages, 'routes' => [], 'children' => $children, 'sorts' => [], 'taxonomy' => []]));

        $wanted = array_merge(['/p/3', '/missing', '/p/3'], array_map(static fn($i) => "/p/{$i}", range(100, 1199)));
        $rows = $store->readMany($wanted);
        self::assertCount(1101, $rows, "Every stored path once, the missing one left out");
        self::assertSame('payload-3', $rows['/p/3']);
        self::assertSame('payload-1199', $rows['/p/1199']);
        self::assertArrayNotHasKey('/missing', $rows);

        self::assertSame(
            ['/p/0' => ['/p/0/c' => ['slug' => 'c0']], '/p/1' => []],
            $store->readChildrenMany(['/p/0', '/p/1', '/missing'])
        );
    }

    /**
     * Everything a theme reads from the pages, in a form that compares across requests.
     */
    private function snapshot(Pages $pages): array
    {
        $children = [];
        foreach ($pages->all() as $page) {
            $children[$page->route()] = $this->titles($page->children());
        }

        return [
            'all' => array_map(static fn($info) => $info['slug'], $pages->all()->toArray()),
            'routes' => $pages->routes(),
            'children' => $children,
            'nav' => $this->titles($pages->root()->children()->visible()),
            'blog' => $this->titles($pages->find('/blog')->collection()),
            'descendants' => $this->descendants($pages),
            'modules' => $this->titles($pages->find('/landing')->collection(['items' => '@self.modules'], false)),
            'category' => $this->titles($this->grav['taxonomy']->findTaxonomy(['category' => 'blog'])),
            'even' => $this->titles($this->grav['taxonomy']->findTaxonomy(['tag' => 'even'])),
            'by-title' => $this->titles($pages->getCollection(['items' => '@root.descendants', 'order' => ['by' => 'title']])),
            'by-header' => $this->titles($pages->getCollection(['items' => '@root.descendants', 'order' => ['by' => 'header.title', 'dir' => 'desc']])),
            'instances' => count($pages->instances()),
        ];
    }

    private function descendants(Pages $pages): array
    {
        return $this->titles($pages->getCollection(['items' => ['@page.descendants' => '/blog'], 'order' => ['by' => 'date', 'dir' => 'desc']]));
    }

    private function titles(iterable $collection): array
    {
        $titles = [];
        foreach ($collection as $page) {
            $titles[] = $page->title();
        }

        return $titles;
    }

    /**
     * Simulate a new request: a fresh Pages service over the same cache.
     *
     * @param bool|null $lazy null keeps the configured value
     */
    private function request(?bool $lazy, ?Pages $pages = null): Pages
    {
        if ($lazy !== null) {
            $this->grav['config']->set('system.pages.lazy_index', $lazy);
        }

        $pages ??= new Pages($this->grav);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        return $pages;
    }

    /**
     * A Pages service that counts its index reads. The root page is loaded before counting starts,
     * as every real request loads it while dispatching.
     */
    private function countingRequest(): Pages
    {
        $pages = $this->request(true, new class($this->grav) extends Pages {
            public int $singleLoads = 0;
            public int $batches = 0;
            public int $childListReads = 0;

            protected function loadIndexedPage(string $path): ?PageInterface
            {
                $this->singleLoads++;

                return parent::loadIndexedPage($path);
            }

            public function prefetch(iterable $paths): void
            {
                $this->batches++;
                parent::prefetch($paths);
            }

            protected function childrenOf(string $path): array
            {
                if ($this->children_lazy && $this->index_store && !array_key_exists($path, $this->children)) {
                    $this->childListReads++;
                }

                return parent::childrenOf($path);
            }
        });
        $pages->root();
        $pages->singleLoads = $pages->batches = $pages->childListReads = 0;

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
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
        clearstatcache();
    }
}
