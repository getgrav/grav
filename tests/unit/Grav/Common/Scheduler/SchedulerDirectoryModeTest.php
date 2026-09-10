<?php

use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\JobHistory;
use Grav\Common\Scheduler\JobQueue;

/**
 * The scheduler's queue and history folders used to be created with a
 * hard-coded 0755, so on a group-writable install (umask 0002, web and CLI
 * users sharing a group) the CLI user could not write into or clear folders
 * the web user had made. They now follow the umask like every other Grav
 * folder (#4295).
 */
class SchedulerDirectoryModeTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    /** @var int */
    private $umask;

    protected function _before(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-scheduler-mode-' . bin2hex(random_bytes(4));
        $this->umask = umask(0002);
    }

    protected function _after(): void
    {
        umask($this->umask);
        Folder::delete($this->root);
    }

    public function testQueueFoldersFollowTheUmask(): void
    {
        new JobQueue($this->root . '/queue');

        foreach (['pending', 'processing', 'failed', 'completed'] as $dir) {
            $this->assertSame('0775', $this->mode($this->root . '/queue/' . $dir), $dir);
        }
    }

    public function testHistoryFoldersFollowTheUmask(): void
    {
        $history = new JobHistory($this->root . '/history');
        (new ReflectionMethod($history, 'storeJobHistory'))->invoke($history, 'job', ['status' => 'success']);

        $this->assertSame('0775', $this->mode($this->root . '/history'));
        $this->assertSame('0775', $this->mode($this->root . '/history/jobs'));
    }

    private function mode(string $path): string
    {
        clearstatcache(true, $path);

        return sprintf('%04o', fileperms($path) & 0777);
    }
}
