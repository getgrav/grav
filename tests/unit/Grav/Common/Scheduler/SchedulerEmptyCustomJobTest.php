<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Job;
use Grav\Common\Scheduler\Scheduler;

/**
 * "Add item" then Save in the admin's custom jobs list stores a row with nothing but the
 * output mode default (`custom_jobs: {1: {output_mode: append}}`). That row took the scheduler
 * down: on the web the missing `command` key broke the webhook, the health check and the admin
 * job list, and on the CLI the empty program name made Process throw a ValueError that stopped
 * every job after it (grav-plugin-scheduler-webhook#10).
 */
class SchedulerEmptyCustomJobTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    /** @var mixed */
    private $savedCustomJobs;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-scheduler-empty-' . bin2hex(random_bytes(4));
        Folder::create($this->root);

        $this->savedCustomJobs = Grav::instance()['config']->get('scheduler.custom_jobs');
    }

    protected function _after(): void
    {
        Grav::instance()['config']->set('scheduler.custom_jobs', $this->savedCustomJobs);
        Folder::delete($this->root);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function incompleteJobProvider(): array
    {
        return [
            'row saved untouched' => [['output_mode' => 'append']],
            'empty command' => [['command' => '', 'at' => '* * * * *']],
            'whitespace command' => [['command' => '   ', 'at' => '* * * * *']],
            'null command' => [['command' => null, 'at' => '* * * * *']],
            'array command' => [['command' => ['ls'], 'at' => '* * * * *']],
            'empty row' => [null],
        ];
    }

    /**
     * @dataProvider incompleteJobProvider
     * @param mixed $incomplete
     */
    public function testACustomJobWithNoCommandIsLeftOut($incomplete): void
    {
        $this->customJobs([
            1 => $incomplete,
            'good-job' => ['command' => 'ls', 'at' => '0 3 * * *'],
        ]);

        $ids = array_map(static fn (Job $job) => $job->getId(), $this->scheduler()->getAllJobs());

        $this->assertSame(['good-job'], $ids);
    }

    /**
     * @dataProvider incompleteJobProvider
     * @param mixed $incomplete
     */
    public function testACustomJobWithNoCommandDoesNotStopTheRun($incomplete): void
    {
        $this->customJobs([1 => $incomplete, 2 => $incomplete]);

        $scheduler = $this->scheduler();
        $ran = false;
        $scheduler->addFunction(static function () use (&$ran) {
            $ran = true;

            return 'ok';
        }, [], 'after-empty')->at('* * * * *');

        $scheduler->run(null, false, Scheduler::RUN_ALL);

        $this->assertTrue($ran);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function emptyCommandProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
        ];
    }

    /**
     * A plugin can still register a command job with nothing in it. Running it records a
     * failure instead of throwing.
     *
     * @dataProvider emptyCommandProvider
     */
    public function testRunningAJobWithNoCommandFailsInsteadOfThrowing(string $command): void
    {
        $job = new Job($command, [], 'no-command-' . bin2hex(random_bytes(3)));

        $this->assertFalse($job->inForeground()->run());
        $this->assertFalse($job->isSuccessful());
        $this->assertSame('This job has no command to run.', $job->getOutput());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blankArgumentsProvider(): array
    {
        return [
            'empty' => [''],
            'single space' => [' '],
            'double space between arguments' => ['-a  -l'],
        ];
    }

    /**
     * An Extra Arguments field left empty is saved as `args: ''`. It must not reach the
     * command as an empty argument (`ls ''` fails with "No such file or directory").
     *
     * @dataProvider blankArgumentsProvider
     */
    public function testBlankArgumentsAreNotPassedToTheCommand(string $args): void
    {
        $job = new Job('ls', $args, 'blank-args-' . bin2hex(random_bytes(3)));

        $job->inForeground()->run();

        $this->assertTrue($job->isSuccessful(), (string) $job->getOutput());
    }

    private function customJobs(array $jobs): void
    {
        Grav::instance()['config']->set('scheduler.custom_jobs', $jobs);
    }

    /**
     * A scheduler with no system jobs, queue, history or logger, its state in a temp folder.
     * Saved jobs are left unloaded so the custom jobs set above are read from config.
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
        $set('modernConfig', []);
        $set('config', []);

        return $scheduler;
    }
}
