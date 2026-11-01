<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Job;
use Grav\Common\Scheduler\Scheduler;

/**
 * A plugin that queued a method callable without an id, such as
 * `addFunction([$this, 'method'], 'my-id')` with the id in the arguments slot, crashed every
 * scheduler run and the admin's job list: the job id came from spl_object_hash(), which only
 * takes objects (getgrav/grav#4380).
 */
class SchedulerMethodCallableTest extends \Codeception\Test\Unit
{
    /** @var int */
    public $calls = 0;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();
        $this->calls = 0;
    }

    public function runJob(): bool
    {
        $this->calls++;

        return true;
    }

    public function testAMethodCallableWithoutAnIdGetsAStableId(): void
    {
        $first = new Job([$this, 'runJob']);
        $second = new Job([$this, 'runJob']);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(md5(self::class . '::runJob'), $first->getId());
    }

    public function testAMethodCallableWithItsIdInTheArgumentsSlotIsQueuedAndRuns(): void
    {
        $scheduler = new Scheduler();
        $job = $scheduler->addFunction([$this, 'runJob'], 'my-plugin-job');

        self::assertContains($job, $scheduler->getAllJobs());

        $job->run();

        self::assertTrue($job->isSuccessful());
        self::assertSame(1, $this->calls);
    }
}
