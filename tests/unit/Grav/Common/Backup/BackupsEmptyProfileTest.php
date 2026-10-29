<?php

use Codeception\Util\Fixtures;
use Grav\Common\Backup\Backups;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Job;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\Event\Event;

/**
 * "Add item" then Save under Backups stores a profile with the defaults but no name. The
 * missing key broke every scheduler listing and run on the web, the same way an empty custom
 * job did (grav-plugin-scheduler-webhook#10).
 */
class BackupsEmptyProfileTest extends \Codeception\Test\Unit
{
    /** @var mixed */
    private $savedProfiles;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $this->savedProfiles = Grav::instance()['config']->get('backups.profiles');
    }

    protected function _after(): void
    {
        Grav::instance()['config']->set('backups.profiles', $this->savedProfiles);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function incompleteProfileProvider(): array
    {
        return [
            'row saved untouched' => [['root' => '/', 'schedule' => 1, 'schedule_at' => '0 3 * * *', 'schedule_environment' => '']],
            'blank name' => [['name' => '  ', 'root' => '/', 'schedule_at' => '0 3 * * *']],
            'no schedule' => [['name' => 'Unscheduled', 'root' => '/']],
            'empty row' => [null],
        ];
    }

    /**
     * @dataProvider incompleteProfileProvider
     * @param mixed $incomplete
     */
    public function testAProfileWithNoNameOrScheduleIsLeftOutOfTheSchedule($incomplete): void
    {
        Grav::instance()['config']->set('backups.profiles', [
            ['name' => 'Default Site Backup', 'root' => '/', 'schedule' => 1, 'schedule_at' => '0 3 * * *'],
            $incomplete,
        ]);

        $scheduler = $this->scheduler();
        (new Backups())->onSchedulerInitialized(new Event(['scheduler' => $scheduler]));

        $ids = array_map(static fn (Job $job) => $job->getId(), $scheduler->getAllJobs());

        $this->assertSame(['default-site-backup'], $ids);
    }

    /**
     * A scheduler with no jobs, queue, history or logger.
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
        $set('status_path', sys_get_temp_dir());
        $set('initialized', true);
        $set('saved_jobs', [true]);
        $set('modernConfig', []);
        $set('config', []);

        return $scheduler;
    }
}
