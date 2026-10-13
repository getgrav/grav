<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;

/**
 * Every cron minute the scheduler wrote "run started" and "no jobs were due" to
 * scheduler.log, so an idle site grew the file by about 115 MB a year with nothing
 * ever rotating it. A run that does nothing now writes nothing, while a run that
 * does work keeps its debug lines (#4355).
 */
class SchedulerLogTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    /** @var string */
    private $logFile;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-scheduler-log-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
        $this->logFile = $this->root . '/scheduler.log';
    }

    protected function _after(): void
    {
        Folder::delete($this->root);
    }

    public function testIdleRunWritesNothing(): void
    {
        $scheduler = $this->scheduler();

        $scheduler->run();
        $scheduler->run();

        $this->assertSame('', $this->log());
    }

    public function testIdleRunAfterBusyOneInTheSameProcessWritesNothing(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->getLogger()->info('busy');

        $scheduler->run();

        $this->assertStringNotContainsString('no jobs were due', $this->log());
    }

    public function testRunThatExecutesAJobKeepsItsDebugLines(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'log-test-job')->at('* * * * *');

        $scheduler->run();

        $log = $this->log();
        $this->assertStringContainsString('scheduler.DEBUG: Scheduler run started - 1 jobs available', $log);
        $this->assertStringContainsString('scheduler.INFO: Scheduler completed:', $log);
    }

    /**
     * A scheduler with no system jobs, no queue or history, and its state and log in a
     * temp folder, so run() touches nothing outside the test.
     */
    private function scheduler(): Scheduler
    {
        $ref = new ReflectionClass(Scheduler::class);
        /** @var Scheduler $scheduler */
        $scheduler = $ref->newInstanceWithoutConstructor();

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

        $logFile = $this->logFile;
        $locator = new class ($logFile) {
            public function __construct(private string $file) {}
            public function findResource(string $uri, bool $absolute = true, bool $first = false): string
            {
                return $this->file;
            }
        };
        $init = $ref->getMethod('initializeLogger');
        $init->setAccessible(true);
        $init->invoke($scheduler, $locator);

        return $scheduler->setRunTrigger('manual');
    }

    private function log(): string
    {
        clearstatcache(true, $this->logFile);

        return file_exists($this->logFile) ? (string) file_get_contents($this->logFile) : '';
    }
}
