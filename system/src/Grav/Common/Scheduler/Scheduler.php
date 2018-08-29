<?php
/**
 * @package    Grav.Common.Scheduler
 * @author     Based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

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
    public function __construct()
    {
        $config = Grav::instance()['config']->get('scheduler.defaults', []);
        $this->config = $config;
        $this->loadSavedJobs();

    }

    /**
     * Load saved jobs from config/scheduler.yaml file
     */
    public function loadSavedJobs()
    {
        if (!$this->jobs) {
            $saved_jobs = (array) Grav::instance()['config']->get('scheduler.jobs', []);

            foreach ($saved_jobs as $j) {
                $args = isset($j['args']) ? $j['args'] : [];
                $job = $this->addCommand($j['command'], $args, $j['id']);

                if (isset($j['at'])) {
                    $job->at($j['at']);
                }

                if (isset($j['output'])) {
                    $job->output($j['output']);
                }

                // store in saved_jobs
                $this->saved_jobs[] = $job;
            }
        }
    }

    /**
     * Get the queued jobs.
     *
     * @return array
     */
    public function getQueuedJobs()
    {
        $background = [];
        $foreground = [];
        foreach ($this->jobs as $job) {
            if ($job->runInBackground()) {
                $background[] = $job;
            } else {
                $foreground[] = $job;
            }
        }
        return [$foreground, $background];
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
     * Queues a static method for execution
     *
     * @param  string  $method  The class::method to execute
     * @param  array  $args  Optional arguments to pass to the php script
     * @param  string  $id   Optional custom identifier
     * @return Job
     */
    public function addStaticMethod($method, $args, $id = null) {
        $job = new Job($method, $args, $id);
        $this->queueJob($job->configure($this->config));
        return $job;
    }

    /**
     * Queues a closure PHP function execution.
     *
     * @param  callable  $fn  The function to execute
     * @param  array  $args  Optional arguments to pass to the php script
     * @param  string  $id   Optional custom identifier
     * @return Job
     */
    public function addClosure(callable $fn, $args = [], $id = null)
    {
        $job = new Job($fn, $args, $id);
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
        list($foreground, $background) = $this->getQueuedJobs();
        $alljobs = array_merge($background, $foreground);
        if (is_null($runTime)) {
            $runTime = new \DateTime('now');
        }
        foreach ($alljobs as $job) {
            if ($job->isDue($runTime)) {
                try {
                    $job->run();
                    $this->pushExecutedJob($job);
                } catch (\Exception $e) {
                    $this->pushFailedJob($job, $e);
                }
            }
        }

        // Finish handling any background jobs
        foreach($background as $job) {
            $job->finalize();
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
        $command = $job->getCommand();
        $args = $job->getArguments();
        // If callable, log the string Closure
        if (is_callable($command)) {
            $command = is_string($command) ? $command : 'Closure';
        }
        $this->addSchedulerVerboseOutput("Executing {$command} {$args}");
        return $job;
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
        $command = $job->getCommand();
        // If callable, log the string Closure
        if (is_callable($command)) {
            $command = is_string($command) ? $command : 'Closure';
        }
        $this->addSchedulerVerboseOutput("{$e->getMessage()}: {$command}");
        return $job;
    }
}
