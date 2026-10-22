<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Filesystem\ZipArchiver;
use Grav\Common\Scheduler\Job;

/**
 * libzip reads every file only in close(), so a file another process shrinks or replaces after
 * addFile() fails the whole archive with "Unexpected length of data". A full scheduler run hit
 * this whenever a background job rewrote a file in the site while the backup was being written:
 * close() was not checked, the next step threw, and that exception ended the whole run.
 */
class ZipArchiverChangedFileTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();
        $this->root = sys_get_temp_dir() . '/grav-zip-changed-' . bin2hex(random_bytes(4));
        Folder::create($this->root . '/site');
        file_put_contents($this->root . '/site/state.yaml', str_repeat('x', 1000));
    }

    protected function _after(): void
    {
        Folder::delete($this->root);
    }

    public function testArchiveIsBuiltAgainWhenAFileChangesOnce(): void
    {
        $archive = $this->root . '/backup.zip';
        $archiver = $this->archiver(1);
        $archiver->setArchive($archive)->setOptions(['exclude_paths' => [], 'exclude_files' => []]);

        $archiver->compress($this->root . '/site');

        self::assertSame(2, $archiver->closes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($archive));
        self::assertSame('rewritten', $zip->getFromName('state.yaml'));
        $zip->close();
    }

    public function testFileThatKeepsChangingFailsWithAClearError(): void
    {
        $archive = $this->root . '/backup.zip';
        $archiver = $this->archiver(PHP_INT_MAX);
        $archiver->setArchive($archive)->setOptions(['exclude_paths' => [], 'exclude_files' => []]);

        try {
            $archiver->compress($this->root . '/site');
            self::fail('compress() should have thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('could not be written', $e->getMessage());
        }

        self::assertSame(3, $archiver->closes);
        self::assertFileDoesNotExist($archive);
    }

    public function testThrowingFunctionJobIsRecordedAsFailed(): void
    {
        $job = new Job(static function () {
            throw new InvalidArgumentException('not a RuntimeException');
        }, [], 'throws');

        $job->run();

        self::assertFalse($job->isSuccessful());
        self::assertSame('not a RuntimeException', $job->getOutput());
    }

    /**
     * An archiver that takes state.yaml away after files are added, the first $changes times, and
     * puts it back once close() has failed. Every libzip fails a close() on a file that is gone, but
     * only newer ones also catch a file that merely shrank (the reported case; CI's libzip does not), so the
     * test does not rely on that.
     */
    private function archiver(int $changes)
    {
        $file = $this->root . '/site/state.yaml';

        return new class($file, $changes) extends ZipArchiver {
            /** @var int */
            public $closes = 0;

            public function __construct(private string $file, private int $changes)
            {
            }

            protected function closeArchive(ZipArchive $zip): ?string
            {
                if ($this->closes++ >= $this->changes) {
                    return parent::closeArchive($zip);
                }

                unlink($this->file);
                $error = parent::closeArchive($zip);
                file_put_contents($this->file, 'rewritten');

                return $error;
            }
        };
    }
}
