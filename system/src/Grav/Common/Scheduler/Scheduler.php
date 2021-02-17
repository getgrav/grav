<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use DateTime;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use InvalidArgumentException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use RocketTheme\Toolbox\File\YamlFile;
use function is_callable;
use function is_string;

/**
 * Class Scheduler
 * @package Grav\Common\Scheduler
 */
class Scheduler
{
    /** @var Job[] The queued jobs. */
    private $jobs = [];

    /** @var Job[] */
    private $saved_jobs = [];

    /** @var Job[] */
    private $executed_jobs = [];

    /** @var Job[] */
    private $failed_jobs = [];

    /** @var Job[] */
    private $jobs_run = [];

    /** @var array */
    private $output_schedule = [];

    /** @var array */
    private $config;

    /** @var string */
    private $status_path;

    /**
     * Create new instance.
     */
    public function __construct()
    {
        $config = Grav::instance()['config']->get('scheduler.defaults', []);
        $this->config = $config;

        $this->status_path = Grav::instance()['locator']->findResource('user-data://scheduler', true, true);
        if (!file_exists($this->status_path)) {
            Folder::create($this->status_path);
        }
    }

    /**
     * Load saved jobs from config/scheduler.yaml file
     *
     * @return $this
     */
    public function loadSavedJobs()
    {
        $this->saved_jobs = [];
        $saved_jobs = (array) Grav::instance()['config']->get('scheduler.custom_jobs', []);

        foreach ($saved_jobs as $id => $j) {
            $args = $j['args'] ?? [];
            $id = Grav::instance()['inflector']->hyphenize($id);
            $job = $this->addCommand($j['command'], $args, $id);

            if (isset($j['at'])) {
                $job->at($j['at']);
            }

            if (isset($j['output'])) {
                $mode = isset($j['output_mode']) && $j['output_mode'] === 'append';
                $job->output($j['output'], $mode);
            }

            if (isset($j['email'])) {
                $job->email($j['email']);
            }

            // store in saved_jobs
            $this->saved_jobs[] = $job;
        }

        return $this;
    }

    /**
     * Get the queued jobs as background/foreground
     *
     * @param bool $all
     * @return array
     */
    public function getQueuedJobs($all = false)
    {
        $background = [];
        $foreground = [];
        foreach ($this->jobs as $job) {
            if ($all || $job->getEnabled()) {
                if ($job->runInBackground()) {
                    $background[] = $job;
                } else {
                    $foreground[] = $job;
                }
            }
        }
        return [$background, $foreground];
    }

    /**
     * Get all jobs if they are disabled or not as one array
     *
     * @return Job[]
     */
    public function getAllJobs()
    {
        [$background, $foreground] = $this->loadSavedJobs()->getQueuedJobs(true);

        return array_merge($background, $foreground);
    }

    /**
     * Get a specific Job based on id
     *
     * @param string $jobid
     * @return Job|null
     */
    public function getJob($jobid)
    {
        $all = $this->getAllJobs();
        foreach ($all as $job) {
            if ($jobid == $job->getId()) {
                return $job;
            }
        }
        return null;
    }

    /**
     * Queues a PHP function execution.
     *
     * @param  callable  $fn  The function to execute
     * @param  array  $args  Optional arguments to pass to the php script
     * @param  string|null  $id   Optional custom identifier
     * @return Job
     */
    public function addFunction(callable $fn, $args = [], $id = null)
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
     * @param  string|null  $id       Optional custom identifier
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
     * @param DateTime|null $runTime Optional, run at specific moment
     * @param bool $force force run even if not due
     */
    public function run(DateTime $runTime = null, $force = false)
    {
        $this->loadSavedJobs();

        [$background, $foreground] = $this->getQueuedJobs(false);
        $alljobs = array_merge($background, $foreground);

        if (null === $runTime) {
            $runTime = new DateTime('now');
        }

        // Star processing jobs
        foreach ($alljobs as $job) {
            if ($job->isDue($runTime) || $force) {
                $job->run();
                $this->jobs_run[] = $job;
            }
        }

        // Finish handling any background jobs
        foreach ($background as $job) {
            $job->finalize();
        }

        // Store states
        $this->saveJobStates();
    }

    /**
     * Reset all collected data of last run.
     *
     * Call before run() if you call run() multiple times.
     *
     * @return $this
     */
    public function resetRun()
    {
        // Reset collected data of last run
        $this->executed_jobs = [];
        $this->failed_jobs = [];
        $this->output_schedule = [];

        return $this;
    }

    /**
     * Get the scheduler verbose output.
     *
     * @param  string  $type  Allowed: text, html, array
     * @return string|array  The return depends on the requested $type
     */
    public function getVerboseOutput($type = 'text')
    {
        switch ($type) {
            case 'text':
                return implode("\n", $this->output_schedule);
            case 'html':
                return implode('<br>', $this->output_schedule);
            case 'array':
                return $this->output_schedule;
            default:
                throw new InvalidArgumentException('Invalid output type');
        }
    }

    /**
     * Remove all queued Jobs.
     *
     * @return $this
     */
    public function clearJobs()
    {
        $this->jobs = [];

        return $this;
    }

    /**
     * Helper to get the full Cron command
     *
     * @return string
     */
    public function getCronCommand()
    {
        $command = $this->getSchedulerCommand();

        return "(crontab -l; echo \"* * * * * {$command} 1>> /dev/null 2>&1\") | crontab -";
    }

    /**
     * @param string|null $php
     * @return string
     */
    public function getSchedulerCommand($php = null)
    {
        $phpBinaryFinder = new PhpExecutableFinder();
        $php = $php ?? $phpBinaryFinder->find();
        $command = 'cd ' . str_replace(' ', '\ ', GRAV_ROOT) . ';' . $php . ' bin/grav scheduler';

        return $command;
    }

    /**
     * Helper to determine if cron job is setup
     * 0 - Crontab Not found
     * 1 - Crontab Found
     * 2 - Error
     *
     * @return int
     */
    public function isCrontabSetup()
    {
        $process = new Process(['crontab', '-l']);
        $process->run();

        if ($process->isSuccessful()) {
            $output = $process->getOutput();
            $command = str_replace('/', '\/', $this->getSchedulerCommand('.*'));
            $full_command = '/^(?!#).* .* .* .* .* ' . $command . '/m';

            return  preg_match($full_command, $output) ? 1 : 0;
        }

        $error = $process->getErrorOutput();

        return Utils::startsWith($error, 'crontab: no crontab') ? 0 : 2;
    }

    /**
     * Get the Job states file
     *
     * @return YamlFile
     */
    public function getJobStates()
    {
        return YamlFile::instance($this->status_path . '/status.yaml');
    }

    /**
     * Save job states to statys file
     *
     * @return void
     */
    private function saveJobStates()
    {
        $now = time();
        $new_states = [];

        foreach ($this->jobs_run as $job) {
            if ($job->isSuccessful()) {
                $new_states[$job->getId()] = ['state' => 'success', 'last-run' => $now];
                $this->pushExecutedJob($job);
            } else {
                $new_states[$job->getId()] = ['state' => 'failure', 'last-run' => $now, 'error' => $job->getOutput()];
                $this->pushFailedJob($job);
            }
        }

        $saved_states = $this->getJobStates();
        $saved_states->save(array_merge($saved_states->content(), $new_states));
    }

    /**
     * Try to determine who's running the process
     *
     * @return false|string
     */
    public function whoami()
    {
        $process = new Process('whoami');
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return $process->getErrorOutput();
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
        $now = '[' . (new DateTime('now'))->format('c') . '] ';
        $this->output_schedule[] = $now . $string;
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
        $this->executed_jobs[] = $job;
        $command = $job->getCommand();
        $args = $job->getArguments();
        // If callable, log the string Closure
        if (is_callable($command)) {
            $command = is_string($command) ? $command : 'Closure';
        }
        $this->addSchedulerVerboseOutput("<green>Success</green>: <white>{$command} {$args}</white>");

        return $job;
    }

    /**
     * Push a failed job.
     *
     * @param  Job  $job
     * @return Job
     */
    private function pushFailedJob(Job $job)
    {
        $this->failed_jobs[] = $job;
        $command = $job->getCommand();
        // If callable, log the string Closure
        if (is_callable($command)) {
            $command = is_string($command) ? $command : 'Closure';
        }
        $output = trim($job->getOutput());
        $this->addSchedulerVerboseOutput("<red>Error</red>:   <white>{$command}</white> → <normal>{$output}</normal>");

        return $job;
    }
}
