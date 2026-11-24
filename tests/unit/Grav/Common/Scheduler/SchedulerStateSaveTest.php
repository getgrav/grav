<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\File\YamlFile;

/**
 * A run reads status.yaml when it starts and saves when it ends. When the :00 run took longer
 * than a minute (a slow job at the top of the hour), the :01 run started before it saved, then
 * saved over it with the copy it read at its start. The jobs the :00 run had recorded were lost,
 * so an hourly job that ran on time looked as if it had missed its run.
 */
class SchedulerStateSaveTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-scheduler-state-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
    }

    protected function _after(): void
    {
        YamlFile::instance($this->root . '/status.yaml')->free();
        Folder::delete($this->root);
    }

    public function testRunKeepsStatesAnotherRunSavedWhileItWasRunning(): void
    {
        $this->writeStates(['every-minute' => $this->state(time() - 60)]);

        $scheduler = $this->scheduler();
        // While this run's job is busy, the overlapping run finishes and records its hourly job.
        $scheduler->addFunction(function () {
            $this->writeStates([
                'every-minute' => $this->state(time() - 60),
                'log-rotate' => $this->state(time()),
            ]);

            return 'ok';
        }, [], 'every-minute')->at('* * * * *');

        $scheduler->run();

        $states = $this->readStates();
        $this->assertArrayHasKey('log-rotate', $states, 'the state the other run saved was overwritten');
        $this->assertArrayHasKey('every-minute', $states);
        $this->assertGreaterThanOrEqual(time() - 5, $states['every-minute']['last-run']);
    }

    public function testWorkerStateSaveKeepsOtherJobs(): void
    {
        $this->writeStates(['log-rotate' => $this->state(time() - 30)]);

        $scheduler = $this->scheduler();
        $job = $scheduler->addFunction(static fn () => 'ok', [], 'queued-job');
        $job->run();

        $method = new ReflectionMethod(Scheduler::class, 'saveJobState');
        $method->invoke($scheduler, $job);

        $states = $this->readStates();
        $this->assertArrayHasKey('log-rotate', $states);
        $this->assertSame('success', $states['queued-job']['state']);
    }

    private function state(int $lastRun): array
    {
        return ['state' => 'success', 'last-run' => $lastRun, 'trigger' => 'cron'];
    }

    private function writeStates(array $states): void
    {
        file_put_contents($this->root . '/status.yaml', \Symfony\Component\Yaml\Yaml::dump($states));
    }

    private function readStates(): array
    {
        return \Symfony\Component\Yaml\Yaml::parseFile($this->root . '/status.yaml') ?: [];
    }

    /**
     * A scheduler with no system jobs, queue, history or logger, its state in a temp folder.
     */
    private function scheduler(): Scheduler
    {
        $ref = new ReflectionClass(Scheduler::class);
        /** @var Scheduler $scheduler */
        $scheduler = $ref->newInstanceWithoutConstructor();

        $set = static function (string $name, $value) use ($ref, $scheduler): void {
            $property = $ref->getProperty($name);
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
