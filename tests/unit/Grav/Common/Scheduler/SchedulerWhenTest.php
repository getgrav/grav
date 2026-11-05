<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Yaml;

/**
 * A plugin keeps a job off with when() (yetisearch-pro's embed job, while semantic search is
 * off). The scheduler used to run it anyway, the job returned without doing anything, and the
 * run was recorded as a failure every time it came due, so the admin and every monitor saw a
 * failing job that had never been attempted.
 */
class SchedulerWhenTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-scheduler-when-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
    }

    protected function _after(): void
    {
        Folder::delete($this->root);
    }

    public function testAJobItsConditionKeepsOffIsNotRunOrRecorded(): void
    {
        $scheduler = $this->scheduler();
        $ran = false;
        $off = $scheduler->addFunction(static function () use (&$ran) { $ran = true; }, [], 'off-job')->at('* * * * *')->when(static fn () => false);
        $scheduler->addFunction(static fn () => 'ok', [], 'on-job')->at('* * * * *');

        $result = $scheduler->processWebhookTrigger('lab-token', null, Scheduler::RUN_DUE);

        $this->assertFalse($off->passesWhen());
        $this->assertFalse($ran);
        $this->assertSame(1, $result['jobs_run'], 'only the job that is on');
        $states = Yaml::parse((string) @file_get_contents($this->root . '/status.yaml')) ?: [];
        $this->assertArrayNotHasKey('off-job', $states, 'no failed run recorded for a job that never ran');
        $this->assertSame('success', $states['on-job']['state'] ?? null);
    }

    public function testAJobItsConditionKeepsOffIsNeverOverdue(): void
    {
        $scheduler = $this->scheduler();
        $job = $scheduler->addFunction(static fn () => 'ok', [], 'off-job')->at('0 * * * *')->when(static fn () => false);
        file_put_contents($this->root . '/status.yaml', "off-job:\n  state: failure\n  last-run: " . (time() - 86400) . "\n  trigger: cron\n");

        $this->assertFalse($scheduler->isOverdue($job));
        $this->assertSame(0, $scheduler->getHealthStatus()['jobs_due']);
    }

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
        $set('webhookEnabled', true);
        $set('webhookToken', 'lab-token');

        return $scheduler;
    }
}
