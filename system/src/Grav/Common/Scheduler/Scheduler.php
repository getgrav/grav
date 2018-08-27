<?php
/**
 * @package    Grav.Common.Scheduler
 * @author     Based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;

class Scheduler
{
    /**
     * The queued jobs.
     *
     * @var array
     */
    private $jobs = [];

    private $saved_jobs = [];

    private $jobs_file;

    /**
     * Successfully executed jobs.
     *
     * @var array
     */
    private $executedJobs = [];

    /**
     * Failed jobs.
     *
     * @var array
     */
    private $failedJobs = [];

    /**
     * The verbose output of the scheduled jobs.
     *
     * @var array
     */
    private $outputSchedule = [];

    /**
     * @var array
     */
    private $config;

    /**
     * Create new instance.
     *
     * @param  array  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->loadSavedJobs();
    }

    public function loadSavedJobs()
    {
        if (!$this->jobs) {
            /** @var CompiledYamlFile $jobs_data */
            $jobs_file = $this->getJobsDataFile();
            $jobs_data = $jobs_file->content();

            if (isset($jobs_data['jobs'])) {
                $jobs = (array) $jobs_data['jobs'];
                foreach ($jobs as $j) {
                    $args = isset($j['args']) ? json_decode($j['args'], true) : [];
                    $job = new Job($j['command'], $args, $j['id']);

                    // store in saved_jobs and main jobs list
                    $this->saved_jobs[] = $job;
                    $this->job = $job;
                }
            }
        }
    }

    public function storeSavedJobs()
    {
        $jobs_data = [];
        $jobs_file = $this->getJobsDataFile();

        foreach ($this->saved_jobs as $job) {
            $jobs_data[] = $job->toArray();
        }

        $jobs_file->save(['jobs' => $jobs_data]);
    }


    /**
     * Get the queued jobs.
     *
     * @return array
     */
    public function getQueuedJobs()
    {
        return $this->prioritiseJobs();
    }

    /**
     * Queues a function execution.
     *
     * @param  callable  $fn  The function to execute
     * @param  array  $args  Optional arguments to pass to the php script
     * @param  string  $id   Optional custom identifier
     * @return Job
     */
    public function addInline(callable $fn, $args = [], $id = null)
    {
        $job = new Job($fn, $args, $id);
        $this->queueJob($job->configure($this->config));
        return $job;
    }

    /**
     * Queues a php script execution.
     *
     * @param  string  $script  The path to the php script to execute
     * @param  string  $bin     Optional path to the php binary
     * @param  array  $args     Optional arguments to pass to the php script
     * @param  string  $id      Optional custom identifier
     * @return Job
     */
    public function addScript($script, $bin = null, $args = [], $id = null)
    {
        if (! is_string($script)) {
            throw new \InvalidArgumentException('The script should be a valid path to a file.');
        }
        $bin = $bin !== null && is_string($bin) && file_exists($bin) ?
            $bin : (PHP_BINARY === '' ? '/usr/bin/php' : PHP_BINARY);
        $job = new Job($bin . ' ' . $script, $args, $id);
        if (! file_exists($script)) {
            $this->pushFailedJob(
                $job,
                new \InvalidArgumentException('The script should be a valid path to a file.')
            );
        }
        $this->queueJob($job->configure($this->config));
        return $job;
    }

    /**
     * Queue a raw shell command.
     *
     * @param  string  $command  The command to execute
     * @param  array  $args      Optional arguments to pass to the command
     * @param  string  $id       Optional custom identifier
     * @return Job
     */
    public function addCommand($command, $args = [], $id = null)
    {
        $job = new Job($command, $args, $id);
        $this->queueJob($job->configure($this->config));
        return $job;
    }

    /**
     * Run the scheduler.
     *
     * @param  \DateTime  $runTime  Optional, run at specific moment
     * @return array  Executed jobs
     */
    public function run(\Datetime $runTime = null)
    {
        $jobs = $this->getQueuedJobs();
        if (is_null($runTime)) {
            $runTime = new \DateTime('now');
        }
        foreach ($jobs as $job) {
            if ($job->isDue($runTime)) {
                try {
                    $job->run();
                    $this->pushExecutedJob($job);
                } catch (\Exception $e) {
                    $this->pushFailedJob($job, $e);
                }
            }
        }
        return $this->getExecutedJobs();
    }

    /**
     * Reset all collected data of last run.
     *
     * Call before run() if you call run() multiple times.
     */
    public function resetRun()
    {
        // Reset collected data of last run
        $this->executedJobs = [];
        $this->failedJobs = [];
        $this->outputSchedule = [];
        return $this;
    }

    /**
     * Get the executed jobs.
     *
     * @return array
     */
    public function getExecutedJobs()
    {
        return $this->executedJobs;
    }

    /**
     * Get the failed jobs.
     *
     * @return array
     */
    public function getFailedJobs()
    {
        return $this->failedJobs;
    }

    /**
     * Get the scheduler verbose output.
     *
     * @param  string  $type  Allowed: text, html, array
     * @return mixed  The return depends on the requested $type
     */
    public function getVerboseOutput($type = 'text')
    {
        switch ($type) {
            case 'text':
                return implode("\n", $this->outputSchedule);
            case 'html':
                return implode('<br>', $this->outputSchedule);
            case 'array':
                return $this->outputSchedule;
            default:
                throw new \InvalidArgumentException('Invalid output type');
        }
    }

    /**
     * Remove all queued Jobs.
     */
    public function clearJobs()
    {
        $this->jobs = [];
        return $this;
    }

    public function getJobsDataFile()
    {
        if (!isset($this->jobs_file)) {
            $path = Grav::instance()['locator']->findResource('user://data') . '/scheduler/jobs.yaml';
            if (!file_exists($path)) {
                touch($path);
            }
            $this->jobs_file = CompiledYamlFile::instance($path);
        }

        return $this->jobs_file;
    }

    /**
     * Queue a job for execution in the correct queue.
     *
     * @param  Job  $job
     * @return void
     */
    private function queueJob(Job $job)
    {
        $this->jobs[] = $job;

        // Store jobs
    }

    /**
     * Prioritise jobs in background.
     *
     * @return array
     */
    private function prioritiseJobs()
    {
        $background = [];
        $foreground = [];
        foreach ($this->jobs as $job) {
            if ($job->canRunInBackground()) {
                $background[] = $job;
            } else {
                $foreground[] = $job;
            }
        }
        return array_merge($background, $foreground);
    }

    /**
     * Push a failed job.
     *
     * @param  Job  $job
     * @param  \Exception  $e
     * @return Job
     */
    private function pushFailedJob(Job $job, \Exception $e)
    {
        $this->failedJobs[] = $job;
        $compiled = $job->compile();
        // If callable, log the string Closure
        if (is_callable($compiled)) {
            $compiled = 'Closure';
        }
        $this->addSchedulerVerboseOutput("{$e->getMessage()}: {$compiled}");
        return $job;
    }

    /**
     * Add an entry to the scheduler verbose output array.
     *
     * @param  string  $string
     * @return void
     */
    private function addSchedulerVerboseOutput($string)
    {
        $now = '[' . (new \DateTime('now'))->format('c') . '] ';
        $this->outputSchedule[] = $now . $string;
        // Print to stdoutput in light gray
        // echo "\033[37m{$string}\033[0m\n";
    }

    /**
     * Push a succesfully executed job.
     *
     * @param  Job  $job
     * @return Job
     */
    private function pushExecutedJob(Job $job)
    {
        $this->executedJobs[] = $job;
        $compiled = $job->compile();
        // If callable, log the string Closure
        if (is_callable($compiled)) {
            $compiled = 'Closure';
        }
        $this->addSchedulerVerboseOutput("Executing {$compiled}");
        return $job;
    }
}
