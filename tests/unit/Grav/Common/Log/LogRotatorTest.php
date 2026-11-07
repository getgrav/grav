<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Log\LogRotator;
use Grav\Common\Log\RotatingStreamHandler;
use Monolog\Logger;

/**
 * Log files in logs/ grew forever (a busy site's grav.log reaches hundreds of MB, and the
 * admin log viewer reads the whole file). They now rotate by size, keeping the live
 * file's name: grav.log -> grav.log.1 -> grav.log.2.gz.
 */
class LogRotatorTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $dir;

    /** @var array|null */
    private $savedRotate;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->dir = sys_get_temp_dir() . '/grav-logrotate-' . bin2hex(random_bytes(4));
        Folder::create($this->dir);
        $this->savedRotate = Grav::instance()['config']->get('system.log.rotate');
    }

    protected function _after(): void
    {
        Grav::instance()['config']->set('system.log.rotate', $this->savedRotate);
        Folder::delete($this->dir);
    }

    public function testRotateShiftsAndCompressesFromTwoOnwards(): void
    {
        $log = $this->dir . '/grav.log';
        file_put_contents($log, "third\n");
        file_put_contents($log . '.1', "second\n");
        $this->gz($log . '.2.gz', "first\n");

        $this->assertTrue(LogRotator::rotate($log, 5, true));

        $this->assertSame('', file_get_contents($log), 'live file is recreated empty');
        $this->assertSame("third\n", file_get_contents($log . '.1'), '.1 stays plain');
        $this->assertFileDoesNotExist($log . '.2');
        $this->assertSame("second\n", gzdecode(file_get_contents($log . '.2.gz')));
        $this->assertSame("first\n", gzdecode(file_get_contents($log . '.3.gz')));
    }

    public function testRotateWithoutCompressionKeepsPlainFiles(): void
    {
        $log = $this->dir . '/grav.log';
        file_put_contents($log, "b\n");
        file_put_contents($log . '.1', "a\n");

        $this->assertTrue(LogRotator::rotate($log, 5, false));

        $this->assertSame("b\n", file_get_contents($log . '.1'));
        $this->assertSame("a\n", file_get_contents($log . '.2'));
        $this->assertFileDoesNotExist($log . '.2.gz');
    }

    public function testOldestFileFallsOff(): void
    {
        $log = $this->dir . '/grav.log';
        file_put_contents($log, "4\n");
        file_put_contents($log . '.1', "3\n");
        $this->gz($log . '.2.gz', "2\n");
        $this->gz($log . '.3.gz', "1\n");

        $this->assertTrue(LogRotator::rotate($log, 3, true));

        $this->assertSame("4\n", file_get_contents($log . '.1'));
        $this->assertSame("3\n", gzdecode(file_get_contents($log . '.2.gz')));
        $this->assertSame("2\n", gzdecode(file_get_contents($log . '.3.gz')));
        $this->assertFileDoesNotExist($log . '.4.gz');
    }

    public function testEmptyOrMissingFileIsNotRotated(): void
    {
        $log = $this->dir . '/grav.log';
        $this->assertFalse(LogRotator::rotate($log));

        touch($log);
        $this->assertFalse(LogRotator::rotate($log));
        $this->assertFileDoesNotExist($log . '.1');
    }

    public function testSizeIsRecheckedUnderTheLock(): void
    {
        $log = $this->dir . '/grav.log';
        file_put_contents($log, str_repeat('x', 100));

        $this->assertFalse(LogRotator::rotate($log, 5, true, 1000), 'under the limit by the time the lock is held');
        $this->assertTrue(LogRotator::rotate($log, 5, true, 100));
    }

    public function testAnotherRotationInProgressIsSkipped(): void
    {
        $log = $this->dir . '/grav.log';
        file_put_contents($log, "x\n");

        $lock = fopen($this->dir . '/.logrotate.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            // flock is per open file description, so a second handle in this process is
            // refused the same way another request would be.
            $this->assertFalse(LogRotator::rotate($log));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $this->assertTrue(LogRotator::rotate($log));
    }

    public function testRotateDirectoryOnlyTouchesLogsOverTheLimit(): void
    {
        file_put_contents($this->dir . '/grav.log', str_repeat('x', 2048));
        file_put_contents($this->dir . '/email.log', 'small');
        file_put_contents($this->dir . '/cache-purge.out', str_repeat('x', 2048));
        file_put_contents($this->dir . '/grav.log.1', str_repeat('x', 4096));

        $result = LogRotator::rotateDirectory($this->dir, ['max_size' => 1024, 'max_files' => 5, 'compress' => true]);

        $this->assertSame(['grav.log'], $result['rotated']);
        $this->assertSame([], $result['skipped']);
        $this->assertFileExists($this->dir . '/grav.log.2.gz');
        $this->assertSame('small', file_get_contents($this->dir . '/email.log'));
        $this->assertFileDoesNotExist($this->dir . '/cache-purge.out.1');
    }

    public function testForceRotatesEveryNonEmptyLog(): void
    {
        file_put_contents($this->dir . '/grav.log', 'a');
        file_put_contents($this->dir . '/email.log', 'b');
        touch($this->dir . '/empty.log');

        $result = LogRotator::rotateDirectory($this->dir, ['max_size' => 1048576, 'max_files' => 5, 'compress' => true], true);

        sort($result['rotated']);
        $this->assertSame(['email.log', 'grav.log'], $result['rotated']);
        $this->assertFileDoesNotExist($this->dir . '/empty.log.1');
    }

    public function testNormalize(): void
    {
        $this->assertSame(
            ['enabled' => true, 'max_size' => 10485760, 'max_files' => 5, 'compress' => true],
            LogRotator::normalize([])
        );
        $this->assertSame(512000, LogRotator::normalize(['max_size' => '500K'])['max_size']);
        $this->assertFalse(LogRotator::normalize(['max_size' => '0'])['enabled'], 'no size limit means no rotation');
        $this->assertFalse(LogRotator::normalize(['enabled' => false])['enabled']);
        $this->assertSame(1, LogRotator::normalize(['max_files' => 0])['max_files']);
    }

    public function testHandlerRotatesOnFirstWriteWhenOverTheLimit(): void
    {
        Grav::instance()['config']->set('system.log.rotate', ['enabled' => true, 'max_size' => '1K', 'max_files' => 5, 'compress' => true]);

        $log = $this->dir . '/grav.log';
        file_put_contents($log, str_repeat("old line\n", 200));

        $logger = new Logger('grav');
        $logger->pushHandler(new RotatingStreamHandler($log));
        $logger->info('first');
        $logger->info('second');

        $this->assertStringContainsString('old line', file_get_contents($log . '.1'));
        $current = file_get_contents($log);
        $this->assertStringContainsString('grav.INFO: first', $current);
        $this->assertStringContainsString('grav.INFO: second', $current);
        $this->assertStringNotContainsString('old line', $current);
    }

    public function testHandlerLeavesTheFileAloneWhenRotationIsOff(): void
    {
        Grav::instance()['config']->set('system.log.rotate', ['enabled' => false, 'max_size' => '1K']);

        $log = $this->dir . '/grav.log';
        file_put_contents($log, str_repeat("old line\n", 200));

        $logger = new Logger('grav');
        $logger->pushHandler(new RotatingStreamHandler($log));
        $logger->info('new');

        $this->assertFileDoesNotExist($log . '.1');
        $this->assertStringContainsString('old line', file_get_contents($log));
    }

    private function gz(string $path, string $content): void
    {
        file_put_contents($path, gzencode($content));
    }
}
