<?php

namespace unit\Grav\Common\Scheduler;

use Grav\Common\Scheduler\Job;

class JobTest extends \Codeception\Test\Unit
{
    /**
     * @dataProvider dataProviderForTestIsOverdue
     */
    public function testIsOverdue($job, $date, $lastRun, $expected)
    {
        $this->assertEquals($expected, $job->isOverdue($date, $lastRun));
    }

    public function dataProviderForTestIsOverdue()
    {
        return [
            'New Job' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => null,
                'lastRun' => null,
                'expected' => false
            ],
            'New Job created 1 hour ago' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => new \DateTime('+1 hour'),
                'lastRun' => null,
                'expected' => true
            ],
            'New Job created 1 minute ago' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => new \DateTime('+1 minute'),
                'lastRun' => null,
                'expected' => false
            ],
            'New Job created 2 minutes ago' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => new \DateTime('+2 minutes'),
                'lastRun' => null,
                'expected' => true
            ],
            'Job created 1 hour ago and last run 1 mn ago' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => new \DateTime('+1 hour'),
                'lastRun' => new \DateTime('+1 minutes'),
                'expected' => true
            ],
            'Job created 1 hour ago and last run 30 mn ago' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => new \DateTime('+1 hour'),
                'lastRun' => new \DateTime('+30 minutes'),
                'expected' => true
            ],
            'Job created 30 minutes ago and last run 1 hour ago' => [
                'job' => (new Job('ls'))->at('* * * * *'),
                'date' => new \DateTime('+30 minutes'),
                'lastRun' => new \DateTime('+1 hour'),
                'expected' => false
            ],
            'New hourly Job' => [
                'job' => (new Job('ls'))->at('0 * * * *'),
                'date' => null,
                'lastRun' => null,
                'expected' => false
            ],
            'New hourly Job created at 2 hours ago' => [
                'job' => (new Job('ls'))->at('0 * * * *'),
                'date' => new \DateTime('+2 hours'),
                'lastRun' => null,
                'expected' => true
            ],
            'Hourly Job created 1 hour ago and last run 30 mn ago' => [
                'job' => (new Job('ls'))->at('0 * * * *'),
                'date' => new \DateTime('+1 hour'),
                'lastRun' => new \DateTime('+30 minutes'),
                'expected' => true
            ],
        ];
    }
}
