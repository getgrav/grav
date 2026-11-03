<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;

/**
 * A cache file the process cannot delete (wrong owner, read-only directory) must not
 * stop the purge, must not be counted as purged, and must be named in the job output
 * instead of "Unknown error" (getgrav/grav#4384).
 */
class CachePurgeFailuresTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;
    /** @var string */
    private $root;
    /** @var string[] */
    private $locked = [];

    protected function _before(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Needs POSIX permissions enforced for the current user.');
        }

        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->root = GRAV_ROOT . '/tests/_output/cache-purge-' . bin2hex(random_bytes(4));
        Folder::create($this->root);

        // Under bin/grav, Debugger::deprecatedErrorHandler() has no previous handler and
        // returns true for every warning, so error_get_last() stays empty. Do the same here.
        set_error_handler(static function () {
            return true;
        });
    }

    protected function _after(): void
    {
        restore_error_handler();
        foreach ($this->locked as $dir) {
            @chmod($dir, 0755);
        }
        if ($this->root && is_dir($this->root)) {
            Folder::delete($this->root);
        }
    }

    public static function lockedLayouts(): array
    {
        return [
            'locked file at the top' => [''],
            'locked file in a subfolder' => ['/59'],
        ];
    }

    /**
     * @dataProvider lockedLayouts
     */
    public function testFolderDeleteNamesTheFileAndTheReason(string $sub): void
    {
        $target = $this->root . '/orphan';
        $file = $this->lockedFile($target . $sub);

        try {
            Folder::delete($target);
            self::fail('Folder::delete() should throw when a file cannot be removed.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString($file, $e->getMessage());
            self::assertStringContainsString('Permission denied', $e->getMessage());
        }
    }

    public function testPurgeContinuesPastUndeletableFilesAndReportsThem(): void
    {
        $cacheRoot = $this->root . '/grav';
        $current = $cacheRoot . '/current';
        Folder::create($current);

        // Two orphaned cache folders older than 90 days; the first one holds a file we cannot delete.
        $lockedFile = $this->lockedFile($cacheRoot . '/aaaaaaaa/59');
        $this->oldFile($cacheRoot . '/aaaaaaaa/60/gone.data');
        $this->oldFile($cacheRoot . '/zzzzzzzz/61/gone.data');
        // Stale files in the current cache folder: one deletable, one not.
        $stale = $this->oldFile($current . '/ab/stale.data');
        $lockedStale = $this->lockedFile($current . '/cd');
        foreach ([$cacheRoot . '/aaaaaaaa', $cacheRoot . '/aaaaaaaa/60', $cacheRoot . '/zzzzzzzz', $cacheRoot . '/zzzzzzzz/61'] as $dir) {
            touch($dir, time() - 100 * 86400);
        }

        $cache = $this->cacheFor($current);
        $message = Cache::purgeJob();

        // The purge went on past the failures.
        self::assertDirectoryDoesNotExist($cacheRoot . '/zzzzzzzz');
        self::assertFileDoesNotExist($cacheRoot . '/aaaaaaaa/60/gone.data');
        self::assertFileDoesNotExist($stale);
        // Files that are still there are not counted and are named in the output.
        self::assertFileExists($lockedFile);
        self::assertFileExists($lockedStale);
        // zzzzzzzz and stale.data; aaaaaaaa is only partly gone, so it does not count.
        self::assertStringContainsString('Purged 2 old cache items', $message);
        self::assertStringContainsString('Could not delete 2 cache paths', $message);
        self::assertStringContainsString($lockedFile, $message);
        self::assertStringContainsString($lockedStale, $message);
        self::assertStringContainsString('Permission denied', $message);
        self::assertSame($cache, $this->grav['cache']);
    }

    public function testScheduledPurgeFailsWithTheReportInsteadOfUnknownError(): void
    {
        $cacheRoot = $this->root . '/grav';
        $current = $cacheRoot . '/current';
        Folder::create($current);
        $lockedFile = $this->lockedFile($cacheRoot . '/aaaaaaaa/59');
        touch($cacheRoot . '/aaaaaaaa', time() - 100 * 86400);
        $this->cacheFor($current);

        $job = new \Grav\Common\Scheduler\Job('Grav\Common\Cache::purgeJob', [true], 'cache-purge');
        $job->run();
        $output = (string) $job->getOutput();

        self::assertFalse($job->isSuccessful());
        self::assertStringContainsString('Purged 0 old cache items', $output);
        self::assertStringContainsString('Could not delete 1 cache path', $output);
        self::assertStringContainsString($lockedFile, $output);
        self::assertStringNotContainsString('Unknown error', $output);
    }

    public function testCacheClearReportsALockedFolderAndClearsTheRest(): void
    {
        $clear = $this->root . '/clear';
        $lockedFile = $this->lockedFile($clear . '/aa');
        $this->oldFile($clear . '/zz/two.php');
        $this->oldFile($clear . '/top.php');
        $stream = 'tests://_output/' . basename($this->root) . '/clear';
        $this->grav['events']->addListener('onBeforeCacheClear', static function ($event) use ($stream) {
            $event['paths'] = [$stream];
        });

        $output = implode("\n", Cache::clearCache('invalidate'));

        self::assertFileExists($lockedFile);
        self::assertFileDoesNotExist($clear . '/zz/two.php');
        self::assertFileDoesNotExist($clear . '/top.php');
        self::assertStringContainsString('ERROR: </red>Unable to delete file: ' . $lockedFile . ' (Permission denied)', $output);
    }

    /**
     * Creates an old file inside a folder the current user cannot write to.
     */
    private function lockedFile(string $dir): string
    {
        $file = $this->oldFile($dir . '/locked.data');
        chmod($dir, 0555);
        $this->locked[] = $dir;

        return $file;
    }

    private function oldFile(string $file): string
    {
        Folder::create(dirname($file));
        file_put_contents($file, 'x');
        touch($file, time() - 100 * 86400);

        return $file;
    }

    /**
     * Points the cache service at a test cache folder.
     */
    private function cacheFor(string $current): Cache
    {
        $cache = $this->grav['cache'];
        $prop = new \ReflectionProperty(Cache::class, 'cache_dir');
        $prop->setAccessible(true);
        $prop->setValue($cache, $current);
        // Keep the real compiled cache out of the purge.
        $this->grav['config']->set('system.cache.compiled_dir', 'tests://_output/' . basename($this->root) . '/compiled');

        return $cache;
    }
}
