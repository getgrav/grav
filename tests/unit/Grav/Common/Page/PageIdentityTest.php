<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageUuidInterface;
use Grav\Common\Page\PageIdentity;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Page ids: a hidden `.page-id` file holding a UUID that travels with a page
 * folder. These tests pin reading and writing it, the duplicate rule, finding
 * a folder after it moved, the pages index, and that nothing is written while
 * `system.pages.uuid` is off.
 */
class PageIdentityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $root;

    /** @var string */
    protected $pagesDir;

    /** @var string[] */
    protected $cacheFilesBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $config = $this->grav['config'];
        $config->set('system.cache.enabled', false);
        $config->set('system.languages.supported', []);
        $config->set('system.home.alias', '/home');
        $config->set('system.pages.dirs', ['page://']);
        $config->set('system.pages.uuid', false);
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();

        $this->root = sys_get_temp_dir() . '/grav-page-identity-' . bin2hex(random_bytes(4));
        $this->pagesDir = $this->root . '/pages';
        $this->writePage('01.home/default.md', 'Home');
        $this->writePage('02.about/default.md', 'About');
        $this->writePage('02.about/team/default.md', 'Team');
        file_put_contents($this->pagesDir . '/02.about/photo.jpg', 'not really a jpeg');
        $this->writePage('03.landing/modular.md', 'Landing');
        $this->writePage('03.landing/_hero/hero.md', 'Hero');

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', $this->pagesDir, false);

        $this->cacheFilesBefore = glob($this->cacheDir() . '/*') ?: [];
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.pages.uuid', false);
        foreach (array_diff(glob($this->cacheDir() . '/*') ?: [], $this->cacheFilesBefore) as $file) {
            @unlink($file);
        }
        if ($this->root) {
            $this->removeDir($this->root);
        }

        parent::tearDown();
    }

    public function testGenerateMakesVersionFourUuids(): void
    {
        $a = PageIdentity::generate();
        $b = PageIdentity::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a);
        self::assertNotSame($a, $b);
        self::assertTrue(PageIdentity::isValid($a));
        self::assertTrue(PageIdentity::isValid(strtoupper($a)));
        self::assertFalse(PageIdentity::isValid('not-a-uuid'));
        self::assertFalse(PageIdentity::isValid(null));
    }

    public function testIsValidRejectsATrailingNewline(): void
    {
        $uuid = PageIdentity::generate();

        self::assertFalse(PageIdentity::isValid($uuid . "\n"), 'the anchor must not match before a trailing newline');
        self::assertFalse(PageIdentity::isValid("\n" . $uuid));
        self::assertFalse(PageIdentity::isValid($uuid . ' '));
    }

    public function testEnsureWaitsForAnotherRequestAssigningTheSameFolder(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open is not available');
        }

        $folder = $this->pagesDir . '/02.about';
        $theirs = PageIdentity::generate();
        $lock = PageIdentity::lockFile($folder);

        // Another request holds the folder's lock and writes its own id.
        $script = <<<'PHP'
[$lock, $file, $uuid] = array_slice($argv, 1);
$h = fopen($lock, 'c');
flock($h, LOCK_EX);
echo "locked\n";
usleep(600000);
file_put_contents($file, $uuid);
flock($h, LOCK_UN);
PHP;
        $child = proc_open(
            [PHP_BINARY, '-r', $script, '--', $lock, $folder . '/' . PageIdentity::FILE, $theirs],
            [1 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($child);
        self::assertSame("locked\n", fgets($pipes[1]));

        // Without the lock this would see no id and write a second one.
        self::assertSame($theirs, PageIdentity::ensure($folder));
        self::assertSame($theirs, PageIdentity::read($folder));

        fclose($pipes[1]);
        proc_close($child);
    }

    public function testCopyIdsCarriesOnlyTheIdsOfAMovedTree(): void
    {
        $source = $this->pagesDir . '/02.about';
        $about = PageIdentity::assign($source);
        $team = PageIdentity::assign($source . '/team');
        touch($source . '/' . PageIdentity::FILE, time() - 3600);
        file_put_contents($source . '/.hidden-note', 'stays behind');
        clearstatcache();

        // What Folder::move() does when rename() fails across filesystems.
        $target = $this->pagesDir . '/05.company';
        \Grav\Common\Filesystem\Folder::copy($source, $target);
        self::assertNull(PageIdentity::read($target), 'Folder::copy() leaves hidden files behind');

        self::assertSame(2, PageIdentity::copyIds($source, $target));
        self::assertSame($about, PageIdentity::read($target));
        self::assertSame($team, PageIdentity::read($target . '/team'));
        self::assertFileDoesNotExist($target . '/.hidden-note', 'only ids are carried over');
        self::assertSame(filemtime($source . '/' . PageIdentity::FILE), filemtime($target . '/' . PageIdentity::FILE), 'the id keeps its age');
    }

    public function testScanIsKeptUpToDateAsIdsAreWritten(): void
    {
        $original = $this->pagesDir . '/02.about';
        $uuid = PageIdentity::assign($original);
        self::assertSame([$original], PageIdentity::find($uuid, true));

        // A second holder written after the scan is known without a new scan.
        PageIdentity::write($this->pagesDir . '/01.home', $uuid);
        self::assertCount(2, PageIdentity::find($uuid));

        PageIdentity::remove($this->pagesDir . '/01.home');
        self::assertSame([$original], PageIdentity::find($uuid));
    }

    public function testReadAndWrite(): void
    {
        $folder = $this->pagesDir . '/02.about';
        self::assertNull(PageIdentity::read($folder));

        $uuid = PageIdentity::assign($folder);
        self::assertSame($uuid, PageIdentity::read($folder));
        self::assertSame($uuid, file_get_contents($folder . '/' . PageIdentity::FILE), 'one line, nothing else');
        self::assertSame([], glob($folder . '/.page-id.*.tmp') ?: [], 'no temporary file is left behind');

        // A trailing newline and upper case (a hand-edited file) still read.
        file_put_contents($folder . '/' . PageIdentity::FILE, strtoupper($uuid) . "\n");
        self::assertSame($uuid, PageIdentity::read($folder));

        file_put_contents($folder . '/' . PageIdentity::FILE, 'garbage');
        self::assertNull(PageIdentity::read($folder));

        self::assertTrue(PageIdentity::remove($folder));
        self::assertNull(PageIdentity::read($folder));
        self::assertFalse(PageIdentity::remove($folder));
    }

    public function testEnsureAssignsOnceAndKeepsTheId(): void
    {
        $folder = $this->pagesDir . '/02.about';
        $uuid = PageIdentity::ensure($folder);

        self::assertTrue(PageIdentity::isValid($uuid));
        self::assertSame($uuid, PageIdentity::ensure($folder));
        self::assertSame($uuid, PageIdentity::ensure($folder, '02.about'));
    }

    public function testFolderAtTheRecordedPathKeepsADuplicatedId(): void
    {
        $original = $this->pagesDir . '/02.about';
        $copy = $this->pagesDir . '/04.about-copy';
        $uuid = PageIdentity::assign($original);
        $this->copyDir($original, $copy);
        // Make the copy's file the older one, so only the recorded path can decide.
        touch($copy . '/' . PageIdentity::FILE, time() - 7200);
        clearstatcache();

        $fresh = PageIdentity::ensure($copy, '02.about');
        self::assertNotSame($uuid, $fresh);
        self::assertSame($uuid, PageIdentity::read($original));
        self::assertSame($uuid, PageIdentity::ensure($original, '02.about'));
    }

    public function testOlderFileKeepsADuplicatedIdWithoutARecord(): void
    {
        $original = $this->pagesDir . '/02.about';
        $copy = $this->pagesDir . '/01.about-copy';
        $uuid = PageIdentity::assign($original);
        touch($original . '/' . PageIdentity::FILE, time() - 3600);
        $this->copyDir($original, $copy);
        clearstatcache();

        // The copy sorts first, but the original's file is older.
        self::assertSame($original, PageIdentity::keeper([$copy, $original]));
        self::assertTrue(PageIdentity::isDuplicate($copy));
        self::assertFalse(PageIdentity::isDuplicate($original));
        self::assertSame($uuid, PageIdentity::ensure($original));
        self::assertNotSame($uuid, PageIdentity::ensure($copy));

        // On a tie the path that sorts first keeps it.
        $time = time() - 60;
        touch($original . '/' . PageIdentity::FILE, $time);
        touch($copy . '/' . PageIdentity::FILE, $time);
        clearstatcache();
        self::assertSame($copy, PageIdentity::keeper([$original, $copy]));
    }

    public function testResolveFollowsAFolderRenamedOutsideGrav(): void
    {
        $uuid = PageIdentity::assign($this->pagesDir . '/02.about/team');
        self::assertSame($this->pagesDir . '/02.about/team', PageIdentity::resolve($uuid, '02.about/team'));

        rename($this->pagesDir . '/02.about', $this->pagesDir . '/05.company');
        clearstatcache();

        self::assertSame($this->pagesDir . '/05.company/team', PageIdentity::resolve($uuid, '02.about/team'));
        self::assertSame($this->pagesDir . '/05.company/team', PageIdentity::resolve(strtoupper($uuid)));
        self::assertNull(PageIdentity::resolve(PageIdentity::generate()));
        self::assertNull(PageIdentity::resolve('nope'));
    }

    public function testRelativeAndAbsolutePaths(): void
    {
        self::assertSame('02.about/team', PageIdentity::relative($this->pagesDir . '/02.about/team'));
        self::assertSame('', PageIdentity::relative($this->pagesDir));
        self::assertSame($this->pagesDir . '/02.about', PageIdentity::absolute('02.about'));
        self::assertSame($this->pagesDir . '/02.about', PageIdentity::absolute($this->pagesDir . '/02.about/'));

        $this->expectException(InvalidArgumentException::class);
        PageIdentity::absolute('02.about/../../etc');
    }

    public function testNothingIsWrittenWhileIdsAreOff(): void
    {
        $folder = $this->pagesDir . '/02.about';

        self::assertFalse(PageIdentity::enabled());
        self::assertNull(PageIdentity::onSaved($folder));
        self::assertFileDoesNotExist($folder . '/' . PageIdentity::FILE);

        $pages = $this->request();
        $page = $pages->find('/about');
        self::assertNotNull($page);
        $page->save();
        self::assertFileDoesNotExist($folder . '/' . PageIdentity::FILE);
        self::assertSame([], $this->idFiles());

        // A file put there by hand still reads, but the index doesn't know it.
        $uuid = PageIdentity::assign($folder);
        $pages = $this->request();
        self::assertInstanceOf(PageUuidInterface::class, $pages->find('/about'));
        self::assertSame($uuid, $pages->find('/about')->uuid());
        self::assertNull($pages->findByUuid($uuid));
    }

    public function testSavingAPageGivesItAnIdWhenEnabled(): void
    {
        PageIdentity::enable();
        self::assertTrue(PageIdentity::enabled());

        $pages = $this->request();
        $page = $pages->find('/about');
        self::assertNull($page->uuid());

        $page->save();
        $uuid = $page->uuid();
        self::assertTrue(PageIdentity::isValid($uuid));

        $page->save();
        self::assertSame($uuid, $page->uuid(), 'a second save keeps the id');

        // The pages folder itself never gets one.
        self::assertNull(PageIdentity::onSaved($this->pagesDir));
        self::assertFileDoesNotExist($this->pagesDir . '/' . PageIdentity::FILE);
    }

    public function testPagesIndexFindsByUuidAndIgnoresTheFile(): void
    {
        $about = PageIdentity::assign($this->pagesDir . '/02.about');
        $hero = PageIdentity::assign($this->pagesDir . '/03.landing/_hero');
        PageIdentity::enable();

        $pages = $this->request();

        self::assertSame($this->pagesDir . '/02.about', $pages->findByUuid($about)->path());
        self::assertSame($this->pagesDir . '/03.landing/_hero', $pages->findByUuid(strtoupper($hero))->path());
        self::assertNull($pages->findByUuid(PageIdentity::generate()));
        self::assertNull($pages->findByUuid('nope'));

        // Not media, and not the page file.
        $page = $pages->find('/about');
        self::assertSame(['photo.jpg'], array_keys($page->media()->all()));
        self::assertSame('default.md', $page->name());

        // The index stops answering for a folder that lost its id.
        PageIdentity::remove($this->pagesDir . '/02.about');
        self::assertNull($pages->findByUuid($about));
    }

    public function testIndexKeepsTheOlderOfTwoFoldersSharingAnId(): void
    {
        $original = $this->pagesDir . '/02.about';
        $uuid = PageIdentity::assign($original);
        touch($original . '/' . PageIdentity::FILE, time() - 3600);
        $this->copyDir($original, $this->pagesDir . '/01.about-copy');
        clearstatcache();
        PageIdentity::enable();

        $pages = $this->request();
        self::assertSame($original, $pages->findByUuid($uuid)->path());

        // ensure() on the copy, with the index answering, renews it.
        self::assertNotSame($uuid, PageIdentity::ensure($this->pagesDir . '/01.about-copy'));
        self::assertSame($uuid, PageIdentity::ensure($original));
    }

    public function testACopyNeverKeepsTheOriginalsId(): void
    {
        $original = $this->pagesDir . '/02.about';
        $uuid = PageIdentity::assign($original);

        $copy = $this->pagesDir . '/04.copy';
        $this->copyDir($original, $copy);
        self::assertNull(PageIdentity::onCopied($copy), 'ids off: the copied file is removed');
        self::assertNull(PageIdentity::read($copy));

        $this->copyDir($original, $copy);
        PageIdentity::enable();
        $fresh = PageIdentity::onCopied($copy);
        self::assertTrue(PageIdentity::isValid($fresh));
        self::assertNotSame($uuid, $fresh);
        self::assertSame($uuid, PageIdentity::read($original));
    }

    public function testAssignAllIsIdempotentAndReportsDuplicates(): void
    {
        $original = $this->pagesDir . '/02.about';
        $uuid = PageIdentity::assign($original);
        touch($original . '/' . PageIdentity::FILE, time() - 3600);
        $this->copyDir($original, $this->pagesDir . '/04.about-copy');
        clearstatcache();

        $dry = PageIdentity::assignAll(true);
        self::assertSame(7, $dry['folders']);
        self::assertSame(1, $dry['existing']);
        self::assertCount(5, $dry['assigned']);
        self::assertSame([$uuid => ['keeper' => $original, 'others' => [$this->pagesDir . '/04.about-copy']]], $dry['duplicates']);
        self::assertCount(2, $this->idFiles(), 'a dry run writes nothing');

        $run = PageIdentity::assignAll();
        self::assertCount(5, $run['assigned']);
        self::assertCount(7, $this->idFiles());
        self::assertSame($uuid, PageIdentity::read($original));
        self::assertNotSame($uuid, PageIdentity::read($this->pagesDir . '/04.about-copy'));

        $again = PageIdentity::assignAll();
        self::assertSame(7, $again['existing']);
        self::assertSame([], $again['assigned']);
        self::assertSame([], $again['duplicates']);
    }

    public function testCachedIndexKeepsIdsAndRebuildsWhenIdsTurnOn(): void
    {
        $uuid = PageIdentity::assign($this->pagesDir . '/02.about');
        $this->useMemoryCache();

        // Cached while ids are off: no ids in the cache entry.
        $this->request();
        self::assertNull($this->request()->findByUuid($uuid));

        // Turning ids on at runtime (a plugin) does not reuse that entry.
        PageIdentity::enable();
        self::assertSame($this->pagesDir . '/02.about', $this->request()->findByUuid($uuid)->path());
        // And the entry written now answers from the cache.
        self::assertSame($this->pagesDir . '/02.about', $this->request()->findByUuid($uuid)->path());

        foreach ([true, false] as $lazy) {
            if ($lazy && !extension_loaded('pdo_sqlite')) {
                continue;
            }
            $this->grav['config']->set('system.pages.lazy_index', $lazy);
            $this->grav['cache']->store = [];
            $this->request();
            self::assertSame($this->pagesDir . '/02.about', $this->request()->findByUuid($uuid)->path(), $lazy ? 'lazy index' : 'blob');
        }
    }

    private function useMemoryCache(): void
    {
        $config = $this->grav['config'];
        $config->set('system.cache.enabled', true);
        $config->set('system.cache.check.method', 'file');
        $config->set('system.cache.check.interval', 0);

        $cache = new class($this->grav) extends \Grav\Common\Cache {
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
    }

    private function request(): Pages
    {
        $pages = new Pages($this->grav);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        return $pages;
    }

    /**
     * @return string[]
     */
    private function idFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->pagesDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getFilename() === PageIdentity::FILE) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function cacheDir(): string
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        return (string)$locator->findResource('cache://compiled/pages', true, true);
    }

    private function writePage(string $path, string $title): void
    {
        $file = $this->pagesDir . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, "---\ntitle: {$title}\n---\n\n{$title} body\n");
        clearstatcache();
    }

    private function copyDir(string $src, string $dst): void
    {
        // Like `cp -R`: hidden files come along.
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        foreach (new FilesystemIterator($src, FilesystemIterator::SKIP_DOTS) as $file) {
            $target = $dst . '/' . $file->getFilename();
            $file->isDir() ? $this->copyDir($file->getPathname(), $target) : copy($file->getPathname(), $target);
        }
        clearstatcache();
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
