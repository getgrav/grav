<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The pages cache is keyed by a hash of the page tree. The change check stats the folders and
 * files the last rebuild saw instead of walking the tree, so it has to notice edits, new pages,
 * deleted pages and renamed folders, and Grav's own writes have to be able to skip the wait,
 * even when they come from a process using another cache driver.
 * The rebuild itself reads pages without the per-file compiled cache and reuses parsed headers.
 */
class PagesChangeCheckTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Cache */
    protected $cache;

    /** @var string */
    protected $root;

    /** @var string */
    protected $pagesDir;

    /** @var string[] */
    protected $scanFilesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $config->set('system.cache.enabled', true);
        $config->set('system.cache.check.method', 'file');
        $config->set('system.cache.check.interval', 0);
        $config->set('system.languages.supported', []);
        $config->set('system.home.alias', '/home');
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();

        // An in-memory cache that honours lifetimes, so the check interval can be tested.
        $this->cache = new class($this->grav) extends Cache {
            /** @var array<string, array{0: mixed, 1: int|null}> */
            public $store = [];

            public function fetch($id)
            {
                if (!isset($this->store[$id])) {
                    return false;
                }
                [$data, $expires] = $this->store[$id];

                return $expires === null || $expires > microtime(true) ? $data : false;
            }

            public function save($id, $data, $lifetime = null)
            {
                $this->store[$id] = [$data, $lifetime ? microtime(true) + $lifetime : null];
            }

            public function delete($id)
            {
                unset($this->store[$id]);

                return true;
            }
        };
        unset($this->grav['cache']);
        $this->grav['cache'] = $this->cache;

        $this->root = sys_get_temp_dir() . '/grav-pages-check-' . bin2hex(random_bytes(4));
        $this->pagesDir = $this->root . '/pages';
        $this->writePage('01.home/default.md', 'Home');
        $this->writePage('02.blog/blog.md', 'Blog');
        $this->writePage('02.blog/post-one/item.md', 'Post One');
        $this->writePage('03.about/default.md', 'About');
        $this->age($this->root);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', $this->pagesDir, false);

        $this->scanFilesBefore = glob($this->scanDir() . '/*') ?: [];
    }

    protected function tearDown(): void
    {
        foreach (array_diff(glob($this->scanDir() . '/*') ?: [], $this->scanFilesBefore) as $file) {
            @unlink($file);
        }
        foreach ($this->pageFiles() as $file) {
            @unlink(CACHE_DIR . 'compiled/files/' . md5($file) . '.md.php');
        }
        $this->removeDir($this->root);

        parent::tearDown();
    }

    public function testEditedPageIsDetected(): void
    {
        self::assertSame('About', $this->request()->find('/about')->title());

        $this->writePage('03.about/default.md', 'About Us');

        self::assertSame('About Us', $this->request()->find('/about')->title());
    }

    public function testCachedPagesLeaveOutTheFrontmatterText(): void
    {
        $pages = $this->request();

        $cached = null;
        foreach ($this->cache->store as [$data]) {
            if (is_array($data) && isset($data[1]) && is_array($data[1])) {
                $cached = $data[1];
            }
        }
        self::assertIsArray($cached, 'The pages index was cached');

        $count = 0;
        foreach ($cached as $page) {
            if ($page instanceof Page) {
                $count++;
                self::assertArrayHasKey("\0*\0frontmatter", (array)$page);
                self::assertNull(((array)$page)["\0*\0frontmatter"]);
            }
        }
        self::assertGreaterThan(3, $count);

        self::assertSame('title: About', $pages->find('/about')->frontmatter());
        self::assertSame('title: About', $this->request()->find('/about')->frontmatter());
    }

    public function testAddedPageIsDetected(): void
    {
        self::assertNull($this->request()->find('/contact'));

        $this->writePage('04.contact/default.md', 'Contact');

        self::assertSame('Contact', $this->request()->find('/contact')->title());
    }

    public function testDeletedPageIsDetected(): void
    {
        self::assertNotNull($this->request()->find('/blog/post-one'));

        // No file anywhere gets newer, so a check on the newest file time misses this.
        $this->removeDir($this->pagesDir . '/02.blog/post-one');

        self::assertNull($this->request()->find('/blog/post-one'));
    }

    public function testRenamedFolderIsDetected(): void
    {
        self::assertNotNull($this->request()->find('/about'));

        rename($this->pagesDir . '/03.about', $this->pagesDir . '/03.company');

        $pages = $this->request();
        self::assertNull($pages->find('/about'));
        self::assertSame('About', $pages->find('/company')->title());
    }

    public function testUnchangedTreeReusesTheCache(): void
    {
        $id = $this->request()->getPagesCacheId();
        $saved = $this->cache->store[$id] ?? null;
        self::assertNotNull($saved);

        $pages = $this->request();

        self::assertSame($id, $pages->getPagesCacheId());
        self::assertSame($saved, $this->cache->store[$id], 'The second request reads the cache instead of rebuilding it');
    }

    public function testMarkChangedSkipsTheCheckInterval(): void
    {
        $this->grav['config']->set('system.cache.check.interval', 600);
        self::assertSame('About', $this->request()->find('/about')->title());

        $this->writePage('03.about/default.md', 'About Us');
        self::assertSame('About', $this->request()->find('/about')->title(), 'The interval hides the edit');

        $this->grav['pages']->markChanged();
        self::assertSame('About Us', $this->request()->find('/about')->title());
    }

    public function testNoneMethodOnlyFollowsMarkChanged(): void
    {
        $this->grav['config']->set('system.cache.check.method', 'none');
        self::assertSame('About', $this->request()->find('/about')->title());

        $this->writePage('03.about/default.md', 'About Us');
        self::assertSame('About', $this->request()->find('/about')->title());

        $this->grav['pages']->markChanged();
        self::assertSame('About Us', $this->request()->find('/about')->title());
    }

    public function testMarkChangedIsSeenByAnotherCacheDriver(): void
    {
        $this->grav['config']->set('system.cache.check.method', 'none');
        self::assertSame('About', $this->request()->find('/about')->title());

        $this->writePage('03.about/default.md', 'About Us');

        // Like the CLI on the file cache while the web uses APCu: the stamp must not live in the driver.
        $other = clone $this->cache;
        $other->store = [];
        unset($this->grav['cache']);
        $this->grav['cache'] = $other;
        (new Pages($this->grav))->markChanged();
        unset($this->grav['cache']);
        $this->grav['cache'] = $this->cache;

        self::assertSame([], $other->store, 'markChanged() writes nothing to the cache driver');
        self::assertFileExists($this->scanDir() . '/change-stamp.txt');
        self::assertSame('About Us', $this->request()->find('/about')->title());
    }

    public function testMissingStampFileIsAnEmptyStamp(): void
    {
        $file = $this->scanDir() . '/change-stamp.txt';
        @unlink($file);
        $id = $this->request()->getPagesCacheId();

        $this->grav['pages']->markChanged();
        $marked = $this->request()->getPagesCacheId();
        self::assertNotSame($id, $marked);

        $this->grav['pages']->markChanged();
        self::assertNotSame($marked, $this->request()->getPagesCacheId(), 'Every call gives a new stamp');

        unlink($file);
        self::assertSame($id, $this->request()->getPagesCacheId());
    }

    public function testFolderMethodDetectsAddedAndDeletedPages(): void
    {
        $this->grav['config']->set('system.cache.check.method', 'folder');
        self::assertNotNull($this->request()->find('/about'));

        $this->removeDir($this->pagesDir . '/03.about');
        $this->writePage('04.contact/default.md', 'Contact');

        $pages = $this->request();
        self::assertNull($pages->find('/about'));
        self::assertNotNull($pages->find('/contact'));
    }

    public function testScanWritesNoCompiledFilesButRenderedPagesStillDo(): void
    {
        $pages = $this->request();
        foreach ($this->pageFiles() as $file) {
            self::assertFileDoesNotExist(CACHE_DIR . 'compiled/files/' . md5($file) . '.md.php');
        }

        // Reading a page outside the scan still goes through the compiled cache as before.
        $page = $pages->find('/about');
        self::assertStringContainsString('About body', $page->rawMarkdown());
        self::assertSame("title: About", $page->frontmatter());
        self::assertFileExists(CACHE_DIR . 'compiled/files/' . md5($page->filePath()) . '.md.php');
    }

    public function testRebuildReusesParsedHeaders(): void
    {
        $file = $this->pagesDir . '/03.about/default.md';

        self::assertTrue(CompiledMarkdownFile::beginScan());
        self::assertFalse(CompiledMarkdownFile::beginScan(), 'Scans do not nest');
        $first = $this->readHeader($file);
        $headers = CompiledMarkdownFile::endScan($changed);
        self::assertTrue($changed);
        self::assertSame(['title' => 'About'], $first);

        CompiledMarkdownFile::beginScan($headers);
        $second = $this->readHeader($file);
        $again = CompiledMarkdownFile::endScan($changed);
        self::assertFalse($changed, 'Nothing was parsed, so the saved headers stay as they are');
        self::assertSame($first, $second);
        self::assertSame($headers, $again);

        $this->writePage('03.about/default.md', 'About Us');
        CompiledMarkdownFile::beginScan($headers);
        $third = $this->readHeader($file);
        $pruned = CompiledMarkdownFile::endScan($changed);
        self::assertTrue($changed);
        self::assertSame(['title' => 'About Us'], $third);
        self::assertCount(1, $pruned, 'Headers no page uses any more are dropped');
    }

    public function testRebuildDoesNotReadUnchangedFiles(): void
    {
        self::assertSame('Home', $this->request()->find('/home')->title());

        // An unreadable file can only keep its title if the rebuild never opens it.
        $home = $this->pagesDir . '/01.home/default.md';
        chmod($home, 0);
        try {
            if (is_readable($home)) {
                self::markTestSkipped('Running as a user that can read any file.');
            }

            $this->writePage('03.about/default.md', 'About Us');
            $pages = $this->request();

            self::assertSame('About Us', $pages->find('/about')->title());
            self::assertSame('Home', $pages->find('/home')->title());
        } finally {
            chmod($home, 0644);
        }
    }

    public function testReusedHeaderStillGivesTheFileText(): void
    {
        $this->request();
        $this->writePage('03.about/default.md', 'About Us');

        // A plugin reading a page's text while the pages are built gets the text from the file.
        $seen = [];
        $listener = function ($event) use (&$seen) {
            $page = $event['page'];
            if ($page->title() === 'Home') {
                $seen = [$page->frontmatter(), trim((string)$page->rawMarkdown())];
            }
        };
        $this->grav['events']->addListener('onPageProcessed', $listener);
        $this->grav['config']->set('system.pages.events.page', true);
        try {
            $pages = $this->request();
        } finally {
            $this->grav['events']->removeListener('onPageProcessed', $listener);
        }

        self::assertSame(['title: Home', 'Home body'], $seen);
        self::assertSame('title: Home', $pages->find('/home')->frontmatter());
        self::assertSame('Home body', trim((string)$pages->find('/home')->rawMarkdown()));
    }

    public function testFileChangedInTheSecondItWasReadIsReadAgain(): void
    {
        // Written now, so the first scan reads it in the same second it was last changed.
        $file = $this->pagesDir . '/03.about/default.md';
        $this->writePage('03.about/default.md', 'About');
        $time = filemtime($file);
        self::assertSame('About', $this->request()->find('/about')->title());

        // Same size and the same modification time: only the content tells them apart.
        $this->writePage('03.about/default.md', 'Abuot');
        touch($file, $time);
        clearstatcache();
        $this->grav['pages']->markChanged();

        self::assertSame('Abuot', $this->request()->find('/about')->title());
    }

    public function testNewPageInAnUnchangedParentIsFound(): void
    {
        self::assertNull($this->request()->find('/blog/post-two'));

        // The blog folder's own time changes; its parent's listing stays reusable.
        $this->writePage('02.blog/post-two/item.md', 'Post Two');

        self::assertSame('Post Two', $this->request()->find('/blog/post-two')->title());
        self::assertSame('Post One', $this->request()->find('/blog/post-one')->title());
    }

    public function testRebuildWaitsForTheLockThenRebuildsItself(): void
    {
        $pages = $this->newPages(0.3);
        $name = 'rebuild-' . md5(json_encode($pages->dirs()));
        $lock = $pages->lock($name, 0);
        self::assertIsResource($lock);

        $waited = false;
        $other = $this->newPages(0.3);
        $start = microtime(true);
        self::assertFalse($other->lock($name, 0.2, $waited));
        self::assertTrue($waited);
        self::assertGreaterThanOrEqual(0.2, microtime(true) - $start);

        // A request that cannot get the lock in time rebuilds the pages itself, as before.
        unset($this->grav['pages']);
        $this->grav['pages'] = $other;
        $start = microtime(true);
        $other->init();
        self::assertGreaterThanOrEqual(0.3, microtime(true) - $start);
        self::assertSame('About', $other->find('/about')->title());

        $pages->unlock($lock);
    }

    public function testLockOfAnEndedProcessIsFree(): void
    {
        $pages = $this->newPages(0.3);
        $file = $this->scanDir() . '/rebuild-test-' . bin2hex(random_bytes(4)) . '.lock';
        $name = basename($file, '.lock');

        // The child takes the lock and dies without releasing it.
        $code = sprintf('$h = fopen(%s, "c"); flock($h, LOCK_EX); posix_kill(getmypid(), 9);', var_export($file, true));
        if (!function_exists('posix_kill')) {
            $code = sprintf('$h = fopen(%s, "c"); flock($h, LOCK_EX); exit(1);', var_export($file, true));
        }
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>/dev/null');

        $waited = false;
        $lock = $pages->lock($name, 1, $waited);
        self::assertIsResource($lock);
        self::assertFalse($waited);
        $pages->unlock($lock);
    }

    public function testLastModifiedFileOnlyMatchesTheGivenExtensions(): void
    {
        $dir = $this->root . '/ext';
        mkdir($dir);
        file_put_contents($dir . '/page.md', 'x');
        file_put_contents($dir . '/page.md.bak', 'x');
        file_put_contents($dir . '/notyaml', 'x');
        touch($dir . '/page.md', 1000000000);
        touch($dir . '/page.md.bak', 1500000000);
        touch($dir . '/notyaml', 1600000000);

        self::assertSame(1000000000, Folder::lastModifiedFile([$dir]));
    }

    /**
     * Simulate a new request: a fresh Pages service over the same cache.
     */
    protected function request(): Pages
    {
        $pages = new Pages($this->grav);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        return $pages;
    }

    protected function newPages(float $timeout): Pages
    {
        return new class($this->grav, $timeout) extends Pages {
            /** @var float */
            private $timeout;

            public function __construct(Grav $grav, float $timeout)
            {
                parent::__construct($grav);
                $this->timeout = $timeout;
            }

            protected function acquireLock(string $name, float $timeout = 0, bool &$waited = false)
            {
                return parent::acquireLock($name, $timeout > 0 ? min($timeout, $this->timeout) : 0, $waited);
            }

            public function lock(string $name, float $timeout, bool &$waited = false)
            {
                return $this->acquireLock($name, $timeout, $waited);
            }

            public function unlock($lock): void
            {
                $this->releaseLock($lock);
            }

            public function dirs(): array
            {
                return $this->getPagesPaths();
            }
        };
    }

    protected function readHeader(string $file): array
    {
        $md = CompiledMarkdownFile::instance($file);
        $header = $md->header();
        $md->free();

        return $header;
    }

    protected function writePage(string $path, string $title): void
    {
        $file = $this->pagesDir . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, "---\ntitle: {$title}\n---\n\n{$title} body\n");
        clearstatcache();
    }

    /**
     * Push every time in the tree an hour back, so changes made by a test are always newer.
     */
    protected function age(string $dir): void
    {
        $past = time() - 3600;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            touch($file->getPathname(), $past);
        }
        touch($dir, $past);
        clearstatcache();
    }

    /**
     * @return string[]
     */
    protected function pageFiles(): array
    {
        if (!is_dir($this->pagesDir)) {
            return [];
        }

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->pagesDir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function scanDir(): string
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $dir = (string)$locator->findResource('cache://compiled/pages', true, true);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    protected function removeDir(string $dir): void
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
