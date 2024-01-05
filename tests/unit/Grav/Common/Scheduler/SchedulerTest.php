<?php

namespace unit\Grav\Common\Scheduler;

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Yaml;
use RocketTheme\Toolbox\File\File;

class SchedulerTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $grav;

    /**
     * @var \Grav\Common\Scheduler\Scheduler
     */
    protected $scheduler;
    private $statusFilePath;

    public function dataProviderForTestIsOverdue()
    {
        return [
            [
                new \DateTime('+2 hours'),
                [
                    'aze45aze' => ['args'=>[], 'command'=>'ls', 'at'=>'0 * * * *'],
                ],
                [
                    'aze45aze' => ['last-run' => strtotime('2021-01-01 00:00:00')],
                ]
            ],
            [
                new \DateTime('+2 hours'),
                [
                    'aze45aze' => ['args'=>[], 'command'=>'ls', 'at'=>'0 * * * *'],
                    'zedz5a4eza' => ['args'=>[], 'command'=>'ls', 'at'=>'*/15 * * * *'],
                ],
                [
                    'aze45aze' => ['last-run' => strtotime('-5 minutes')],
                ]
            ],
        ];
    }

    protected function _before()
    {
        $this->grav = Fixtures::get('grav')();
        $this->scheduler = new Scheduler();
        $this->statusFilePath = Grav::instance()['locator']->findResource('user-data://scheduler', true, true).'/status.yaml';
    }

    protected function _after()
    {
        if (file_exists($this->statusFilePath)) {
            unlink($this->statusFilePath);
        }
    }

    /**
     * @dataProvider dataProviderForTestIsOverdue
     */
    public function testIsOverdue($date, $jobs, $status){
        $file = $this->scheduler->getJobStates();
        $file->save($status);
        $this->grav['config']->set('scheduler.custom_jobs', $jobs);
        $this->scheduler->run($date, false, true);
        $this->assertFileExists($this->statusFilePath);
        $this->assertFileIsReadable($this->statusFilePath);
        foreach ($jobs as $id => $job) {
            $this->assertStringContainsString($id, file_get_contents($this->statusFilePath));
        }
    }
}
