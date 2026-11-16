<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;

/**
 * Many hosts disable proc_open for the web server's PHP while cron's PHP keeps it. A job that
 * calls a PHP function (a backup, a cache purge) never needs it, so a run started from the admin
 * there runs those jobs and skips the command jobs, instead of failing them, which would record
 * a failure for a job cron runs fine (getgrav/grav#4397).
 */
class SchedulerNoProcessTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-scheduler-noprocess-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
    }

    protected function _after(): void
    {
        Folder::delete($this->root);
    }

    public function testFunctionJobsCanRunWithoutProcOpen(): void
    {
        $scheduler = $this->scheduler();
        $function = $scheduler->addFunction(static fn () => 'ok', [], 'function-job');
        $command = $scheduler->addCommand('bin/grav', ['--version'], 'command-job');

        $this->assertTrue(NoProcessScheduler::canRun($function));
        $this->assertFalse(NoProcessScheduler::canRun($command));
        $this->assertTrue(Scheduler::canRun($command));
    }

    public function testAManualRunSkipsCommandJobsItCannotStart(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'function-job');
        $scheduler->addCommand('bin/grav', ['--version'], 'command-job');

        $scheduler->setRunTrigger('manual');
        $scheduler->run(null, false, Scheduler::RUN_ALL);

        $ran = array_map(static fn ($job) => $job->getId(), $scheduler->getJobsRun());
        $this->assertSame(['function-job'], $ran);

        // Nothing is recorded for the skipped job, so its last cron result still stands.
        $states = (array) $scheduler->getJobStates()->content();
        $this->assertArrayHasKey('function-job', $states);
        $this->assertArrayNotHasKey('command-job', $states);
    }

    private function scheduler(): NoProcessScheduler
    {
        $ref = new ReflectionClass(Scheduler::class);
        /** @var NoProcessScheduler $scheduler */
        $scheduler = (new ReflectionClass(NoProcessScheduler::class))->newInstanceWithoutConstructor();

        $set = static function (string $name, $value) use ($ref, $scheduler): void {
            $property = $ref->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($scheduler, $value);
        };
        $set('status_path', $this->root);
        $set('initialized', true);
        $set('saved_jobs', [true]);
        $set('modernConfig', []);
        $set('config', []);

        return $scheduler;
    }
}

class NoProcessScheduler extends Scheduler
{
    public static function isProcessAvailable(): bool
    {
        return false;
    }
}
