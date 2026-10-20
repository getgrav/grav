<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;

/**
 * A webhook is usually pinged by a host cron or an uptime service every 5, 15 or 60 minutes,
 * and rarely on the exact minute. Matching each job against the minute of the ping meant a
 * job set for 02:00 never ran when the 02:00 ping arrived at 02:01, and a job set for :10 never
 * ran at all on an hourly ping, while the admin listed both as Pending and the health check
 * said "healthy" (grav-plugin-scheduler-webhook#9).
 */
class SchedulerCatchUpTest extends \Codeception\Test\Unit
{
    /** @var string */
    private $root;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->root = sys_get_temp_dir() . '/grav-scheduler-catchup-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
    }

    protected function _after(): void
    {
        Folder::delete($this->root);
    }

    public function testWebhookRunsAJobWhoseSlotWasMissed(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'missed-job')->at($this->hourlyAt(-20));
        $this->state('missed-job', time() - 7200);

        $result = $scheduler->processWebhookTrigger('lab-token');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['jobs_run']);
    }

    public function testWebhookDoesNotRerunAJobThatAlreadyRanForItsSlot(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'done-job')->at($this->hourlyAt(-20));
        $this->state('done-job', time() - 60);

        $result = $scheduler->processWebhookTrigger('lab-token');

        $this->assertSame(0, $result['jobs_run']);
    }

    public function testWebhookCanStillAskForDueJobsOnly(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'missed-job')->at($this->hourlyAt(-20));
        $this->state('missed-job', time() - 7200);

        $result = $scheduler->processWebhookTrigger('lab-token', null, Scheduler::RUN_DUE);

        $this->assertSame(0, $result['jobs_run']);
    }

    public function testCatchUpRunsDoNotOverlap(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'missed-job')->at($this->hourlyAt(-20));
        $this->state('missed-job', time() - 7200);

        // Another catch-up run still holds the lock, its long job not yet recorded.
        $held = fopen($this->root . '/run.lock', 'c');
        flock($held, LOCK_EX);

        $result = $scheduler->processWebhookTrigger('lab-token');

        flock($held, LOCK_UN);
        fclose($held);

        $this->assertSame(0, $result['jobs_run']);
    }

    public function testHealthWarnsWhenAJobMissedItsSlot(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'missed-job')->at($this->hourlyAt(-20));
        $this->state('missed-job', time() - 7200);

        $health = $scheduler->getHealthStatus();

        $this->assertSame('warning', $health['status']);
        $this->assertSame(1, $health['jobs_due']);
    }

    public function testHealthIsHealthyWhenEveryJobKeptUp(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->addFunction(static fn () => 'ok', [], 'every-minute')->at('* * * * *');
        $this->state('every-minute', time() - 30);
        file_put_contents($this->root . '/last_run.txt', date('Y-m-d H:i:s', time() - 30));

        $health = $scheduler->getHealthStatus();

        $this->assertSame('healthy', $health['status']);
        $this->assertSame(0, $health['jobs_due']);
    }

    /**
     * An hourly expression whose slot is $offset minutes from now, so it is never due this
     * minute and its last slot is always inside the past hour.
     */
    private function hourlyAt(int $offset): string
    {
        $minute = ((int) date('i') + $offset + 60) % 60;

        return $minute . ' * * * *';
    }

    private function state(string $id, int $lastRun): void
    {
        $file = $this->root . '/status.yaml';
        file_put_contents($file, "{$id}:\n  state: success\n  last-run: {$lastRun}\n  trigger: cron\n");
    }

    /**
     * A scheduler with no system jobs, queue, history or logger, its state in a temp folder,
     * and the webhook enabled with a known token.
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
        $set('webhookEnabled', true);
        $set('webhookToken', 'lab-token');

        return $scheduler;
    }
}
