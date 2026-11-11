<?php

use Codeception\Util\Fixtures;
use Grav\Common\Backup\Backups;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Scheduler;
use RocketTheme\Toolbox\Event\Event;

/**
 * A backup profile's Scheduled Backup switch decides whether its job runs. Classic admin wrote a
 * `scheduler.status` entry for every job each time its Scheduler page was saved (`disabled` from
 * 1.9.18 to 1.10.49.3, `enabled` since), and that leftover entry overrode the switch: backups
 * stopped with Scheduled Backup on, or ran with it off (grav#4397).
 */
class BackupsScheduleFlagTest extends \Codeception\Test\Unit
{
    /** @var mixed */
    private $savedProfiles;
    /** @var mixed */
    private $savedStatus;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();

        $config = Grav::instance()['config'];
        $this->savedProfiles = $config->get('backups.profiles');
        $this->savedStatus = $config->get('scheduler.status');
    }

    protected function _after(): void
    {
        $config = Grav::instance()['config'];
        $config->set('backups.profiles', $this->savedProfiles);
        $config->set('scheduler.status', $this->savedStatus);
    }

    /**
     * @return array<string, array{0: mixed, 1: array<string, string>, 2: bool}>
     */
    public static function scheduleProvider(): array
    {
        return [
            'on, no status entry' => [true, [], true],
            'off, no status entry' => [false, [], false],
            'on, stale disabled entry' => [true, ['default-site-backup' => 'disabled'], true],
            'off, stale enabled entry' => [false, ['default-site-backup' => 'enabled'], false],
            'on, saved as 1' => [1, ['default-site-backup' => 'disabled'], true],
            'off, saved as 0' => [0, ['default-site-backup' => 'enabled'], false],
        ];
    }

    /**
     * @dataProvider scheduleProvider
     * @param mixed $schedule
     * @param array<string, string> $status
     */
    public function testTheProfileSwitchDecidesWhetherTheBackupJobIsEnabled($schedule, array $status, bool $expected): void
    {
        $config = Grav::instance()['config'];
        $config->set('scheduler.status', $status);
        $config->set('backups.profiles', [
            ['name' => 'Default Site Backup', 'root' => '/', 'schedule' => $schedule, 'schedule_at' => '0 3 * * *'],
        ]);

        $scheduler = $this->scheduler();
        (new Backups())->onSchedulerInitialized(new Event(['scheduler' => $scheduler]));

        $job = $scheduler->getJob('default-site-backup');
        $this->assertNotNull($job);
        $this->assertSame($expected, $job->getEnabled());
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
