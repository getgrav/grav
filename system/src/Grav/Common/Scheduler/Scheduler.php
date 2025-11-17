<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
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
use Symfony\Component\Yaml\Yaml;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
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
    
    // Modern features (backward compatible - disabled by default)
    /** @var JobQueue|null */
    protected $jobQueue = null;
    
    /** @var array */
    protected $workers = [];
    
    /** @var int */
    protected $maxWorkers = 1;
    
    /** @var bool */
    protected $webhookEnabled = false;
    
    /** @var string|null */
    protected $webhookToken = null;
    
    /** @var bool */
    protected $healthEnabled = true;
    
    /** @var string */
    protected $queuePath;
    
    /** @var string */
    protected $historyPath;
    
    /** @var Logger|null */
    protected $logger = null;
    
    /** @var array */
    protected $modernConfig = [];

    /**
     * Create new instance.
     */
    public function __construct()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('scheduler.defaults', []);
        $this->config = $config;

        $locator = $grav['locator'];
        $this->status_path = $locator->findResource('user-data://scheduler', true, true);
        if (!file_exists($this->status_path)) {
            Folder::create($this->status_path);
        }
        
        // Initialize modern features (always enabled now)
        $this->modernConfig = $grav['config']->get('scheduler.modern', []);
        // Always initialize modern features - they're now part of core
        $this->initializeModernFeatures($locator);
    }

    /**
     * Load saved jobs from config/scheduler.yaml file
     *
     * @return $this
     */
    public function loadSavedJobs()
    {
        // Only load saved jobs if they haven't been loaded yet
        if (!empty($this->saved_jobs)) {
            return $this;
        }
        
        $this->saved_jobs = [];
        $saved_jobs = (array) Grav::instance()['config']->get('scheduler.custom_jobs', []);

        foreach ($saved_jobs as $id => $j) {
            $args = $j['args'] ?? [];
            $id = Grav::instance()['inflector']->hyphenize($id);
            
            // Check if job already exists to prevent duplicates
            $existingJob = null;
            foreach ($this->jobs as $existingJobItem) {
                if ($existingJobItem->getId() === $id) {
                    $existingJob = $existingJobItem;
                    break;
                }
            }
            
            if ($existingJob) {
                // Job already exists, just update saved_jobs reference
                $this->saved_jobs[] = $existingJob;
                continue;
            }
            
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
     * Get the job queue
     * 
     * @return JobQueue|null
     */
    public function getJobQueue(): ?JobQueue
    {
        return $this->jobQueue;
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
        // Initialize system jobs if not already done
        $grav = Grav::instance();
        if (count($this->jobs) === 0) {
            // Trigger event to load system jobs (cache-purge, cache-clear, backups, etc.)
            $grav->fireEvent('onSchedulerInitialized', new \RocketTheme\Toolbox\Event\Event(['scheduler' => $this]));
        }
        
        $this->loadSavedJobs();

        [$background, $foreground] = $this->getQueuedJobs(false);
        $alljobs = array_merge($background, $foreground);

        if (null === $runTime) {
            $runTime = new DateTime('now');
        }

        // Log scheduler run
        if ($this->logger) {
            $jobCount = count($alljobs);
            $forceStr = $force ? ' (forced)' : '';
            $this->logger->debug("Scheduler run started - {$jobCount} jobs available{$forceStr}", [
                'time' => $runTime->format('Y-m-d H:i:s')
            ]);
        }

        // Process jobs based on modern features
        if ($this->jobQueue && ($this->modernConfig['queue']['enabled'] ?? false)) {
            // Queue jobs for processing
            $queuedCount = 0;
            foreach ($alljobs as $job) {
                if ($job->isDue($runTime) || $force) {
                    // Add to queue for concurrent processing
                    $this->jobQueue->push($job);
                    $queuedCount++;
                }
            }
            
            if ($this->logger && $queuedCount > 0) {
                $this->logger->debug("Queued {$queuedCount} job(s) for processing");
            }
            
            // Process queue with workers
            $this->processJobsWithWorkers();
            
            // When using queue, states are saved by executeJob when jobs complete
            // Don't save states here as jobs may still be processing
        } else {
            // Legacy processing (one at a time)
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

            // Store states for legacy mode
            $this->saveJobStates();
            
            // Save history if enabled
            if (($this->modernConfig['history']['enabled'] ?? false) && $this->historyPath) {
                $this->saveJobHistory();
            }
        }

        // Log run summary
        if ($this->logger) {
            $successCount = 0;
            $failureCount = 0;
            $failedJobNames = [];
            $executedJobs = array_merge($this->executed_jobs, $this->jobs_run);
            
            foreach ($executedJobs as $job) {
                if ($job->isSuccessful()) {
                    $successCount++;
                } else {
                    $failureCount++;
                    $failedJobNames[] = $job->getId();
                }
            }
            
            if (count($executedJobs) > 0) {
                if ($failureCount > 0) {
                    $failedList = implode(', ', $failedJobNames);
                    $this->logger->warning("Scheduler completed: {$successCount} succeeded, {$failureCount} failed (failed: {$failedList})");
                } else {
                    $this->logger->info("Scheduler completed: {$successCount} job(s) succeeded");
                }
            } else {
                $this->logger->debug('Scheduler completed: no jobs were due');
            }
        }

        // Store run date
        file_put_contents("logs/lastcron.run", (new DateTime("now"))->format("Y-m-d H:i:s"), LOCK_EX);
        
        // Update last run timestamp for health checks
        $this->updateLastRun();
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
     * Helper to determine if cron-like job is setup
     * 0 - Crontab Not found
     * 1 - Crontab Found
     * 2 - Error
     *
     * @return int
     */
    public function isCrontabSetup()
    {
        // Check for external triggers
        $last_run = @file_get_contents("logs/lastcron.run");
        if (time() - strtotime($last_run) < 120){
            return 1;
        }

        // No external triggers found, so do legacy cron checks
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
        $process = new Process(['whoami']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return $process->getErrorOutput();
    }


    /**
     * Initialize modern features
     * 
     * @param mixed $locator
     * @return void
     */
    protected function initializeModernFeatures($locator): void
    {
        // Set up paths
        $this->queuePath = $this->modernConfig['queue']['path'] ?? 'user-data://scheduler/queue';
        $this->queuePath = $locator->findResource($this->queuePath, true, true);
        
        $this->historyPath = $this->modernConfig['history']['path'] ?? 'user-data://scheduler/history';
        $this->historyPath = $locator->findResource($this->historyPath, true, true);
        
        // Create directories if they don't exist
        if (!file_exists($this->queuePath)) {
            Folder::create($this->queuePath);
        }
        
        if (!file_exists($this->historyPath)) {
            Folder::create($this->historyPath);
        }
        
        // Initialize job queue (always enabled)
        $this->jobQueue = new JobQueue($this->queuePath);
        
        // Initialize scheduler logger
        $this->initializeLogger($locator);
        
        // Configure workers (default to 4 for concurrent processing)
        $this->maxWorkers = $this->modernConfig['workers'] ?? 4;
        
        // Configure webhook
        $this->webhookEnabled = $this->modernConfig['webhook']['enabled'] ?? false;
        $this->webhookToken = $this->modernConfig['webhook']['token'] ?? null;
        
        // Configure health check
        $this->healthEnabled = $this->modernConfig['health']['enabled'] ?? true;
    }
    
    /**
     * Get the job queue
     * 
     * @return JobQueue|null
     */
    public function getQueue(): ?JobQueue
    {
        return $this->jobQueue;
    }
    
    /**
     * Initialize the scheduler logger
     * 
     * @param $locator
     * @return void
     */
    protected function initializeLogger($locator): void
    {
        $this->logger = new Logger('scheduler');
        
        // Single scheduler log file - all levels
        $logFile = $locator->findResource('log://scheduler.log', true, true);
        $this->logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }
    
    /**
     * Get the scheduler logger
     * 
     * @return Logger|null
     */
    public function getLogger(): ?Logger
    {
        return $this->logger;
    }
    
    /**
     * Check if webhook is enabled
     * 
     * @return bool
     */
    public function isWebhookEnabled(): bool
    {
        return $this->webhookEnabled;
    }
    
    /**
     * Get active trigger methods
     * 
     * @return array
     */
    public function getActiveTriggers(): array
    {
        $triggers = [];
        
        $cronStatus = $this->isCrontabSetup();
        if ($cronStatus === 1) {
            $triggers[] = 'cron';
        }
        
        // Check if webhook is enabled
        if ($this->isWebhookEnabled()) {
            $triggers[] = 'webhook';
        }
        
        return $triggers;
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
    
    /**
     * Process jobs using multiple workers
     * 
     * @return void
     */
    protected function processJobsWithWorkers(): void
    {
        if (!$this->jobQueue) {
            return;
        }
        
        // Process all queued jobs
        while (!$this->jobQueue->isEmpty()) {
            // Wait if we've reached max workers
            while (count($this->workers) >= $this->maxWorkers) {
                foreach ($this->workers as $workerId => $worker) {
                    $process = null;
                    if (is_array($worker) && isset($worker['process'])) {
                        $process = $worker['process'];
                    } elseif ($worker instanceof Process) {
                        $process = $worker;
                    }
                    
                    if ($process instanceof Process && !$process->isRunning()) {
                        // Finalize job if needed
                        if (is_array($worker) && isset($worker['job'])) {
                            $worker['job']->finalize();
                            
                            // Save job state
                            $this->saveJobState($worker['job']);
                            
                            // Update queue status
                            if (isset($worker['queueId']) && $this->jobQueue) {
                                if ($worker['job']->isSuccessful()) {
                                    $this->jobQueue->complete($worker['queueId']);
                                } else {
                                    $this->jobQueue->fail($worker['queueId'], $worker['job']->getOutput() ?: 'Job failed');
                                }
                            }
                        }
                        unset($this->workers[$workerId]);
                    }
                }
                if (count($this->workers) >= $this->maxWorkers) {
                    usleep(100000); // Wait 100ms
                }
            }
            
            // Get next job from queue
            $queueItem = $this->jobQueue->popWithId();
            if ($queueItem) {
                $this->executeJob($queueItem['job'], $queueItem['id']);
            }
        }
        
        // Wait for all remaining workers to complete
        foreach ($this->workers as $workerId => $worker) {
            if (is_array($worker) && isset($worker['process'])) {
                $process = $worker['process'];
                if ($process instanceof Process) {
                    $process->wait();
                    
                    // Finalize and save state for background jobs
                    if (isset($worker['job'])) {
                        $worker['job']->finalize();
                        $this->saveJobState($worker['job']);
                        
                        // Log background job completion
                        if ($this->logger) {
                            $job = $worker['job'];
                            $jobId = $job->getId();
                            $command = is_string($job->getCommand()) ? $job->getCommand() : 'Closure';
                            
                            if ($job->isSuccessful()) {
                                $execTime = method_exists($job, 'getExecutionTime') ? $job->getExecutionTime() : null;
                                $timeStr = $execTime ? sprintf(' (%.2fs)', $execTime) : '';
                                $this->logger->info("Job '{$jobId}' completed successfully{$timeStr}", [
                                    'command' => $command,
                                    'background' => true
                                ]);
                            } else {
                                $error = trim($job->getOutput()) ?: 'Unknown error';
                                $this->logger->error("Job '{$jobId}' failed: {$error}", [
                                    'command' => $command,
                                    'background' => true
                                ]);
                            }
                        }
                    }
                    
                    // Update queue status for background jobs
                    if (isset($worker['queueId']) && $this->jobQueue) {
                        $job = $worker['job'];
                        if ($job->isSuccessful()) {
                            $this->jobQueue->complete($worker['queueId']);
                        } else {
                            $this->jobQueue->fail($worker['queueId'], $job->getOutput() ?: 'Job execution failed');
                        }
                    }
                    
                    unset($this->workers[$workerId]);
                }
            } elseif ($worker instanceof Process) {
                // Legacy format
                $worker->wait();
                unset($this->workers[$workerId]);
            }
        }
    }
    
    /**
     * Process existing queued jobs
     * 
     * @return void
     */
    protected function processQueuedJobs(): void
    {
        if (!$this->jobQueue) {
            return;
        }
        
        // Process any existing queued jobs from previous runs
        while (!$this->jobQueue->isEmpty() && count($this->workers) < $this->maxWorkers) {
            $job = $this->jobQueue->pop();
            if ($job) {
                $this->executeJob($job);
            }
        }
    }
    
    /**
     * Execute a job
     * 
     * @param Job $job
     * @param string|null $queueId Queue ID if job came from queue
     * @return void
     */
    protected function executeJob(Job $job, ?string $queueId = null): void
    {
        $job->run();
        $this->jobs_run[] = $job;
        
        // Save job state after execution
        $this->saveJobState($job);
        
        // Check if job runs in background
        if ($job->runInBackground()) {
            // Background job - track it for later completion
            $process = $job->getProcess();
            if ($process && $process->isStarted()) {
                $this->workers[] = [
                    'process' => $process,
                    'job' => $job,
                    'queueId' => $queueId
                ];
                // Don't update queue status yet - will be done when process completes
                return;
            }
        }
        
        // Foreground job or background job that didn't start - update queue status immediately
        if ($queueId && $this->jobQueue) {
            // Job has already been finalized if it ran in foreground
            if (!$job->runInBackground()) {
                $job->finalize();
            }
            
            if ($job->isSuccessful()) {
                // Move from processing to completed
                $this->jobQueue->complete($queueId);
            } else {
                // Move from processing to failed
                $this->jobQueue->fail($queueId, $job->getOutput() ?: 'Job execution failed');
            }
        }
        
        // Log foreground jobs immediately
        if (!$job->runInBackground() && $this->logger) {
            $jobId = $job->getId();
            $command = is_string($job->getCommand()) ? $job->getCommand() : 'Closure';
            
            if ($job->isSuccessful()) {
                $execTime = method_exists($job, 'getExecutionTime') ? $job->getExecutionTime() : null;
                $timeStr = $execTime ? sprintf(' (%.2fs)', $execTime) : '';
                $this->logger->info("Job '{$jobId}' completed successfully{$timeStr}", [
                    'command' => $command
                ]);
            } else {
                $error = trim($job->getOutput()) ?: 'Unknown error';
                $this->logger->error("Job '{$jobId}' failed: {$error}", [
                    'command' => $command
                ]);
            }
        }
    }
    
    /**
     * Save state for a single job
     * 
     * @param Job $job
     * @return void
     */
    protected function saveJobState(Job $job): void
    {
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $statusFile = $locator->findResource('user-data://scheduler/status.yaml', true, true);
        
        $status = [];
        if (file_exists($statusFile)) {
            $status = Yaml::parseFile($statusFile) ?: [];
        }
        
        // Update job status
        $status[$job->getId()] = [
            'state' => $job->isSuccessful() ? 'success' : 'failure',
            'last-run' => time(),
        ];
        
        // Add error if job failed
        if (!$job->isSuccessful()) {
            $output = $job->getOutput();
            if ($output) {
                $status[$job->getId()]['error'] = $output;
            } else {
                $status[$job->getId()]['error'] = null;
            }
        }
        
        file_put_contents($statusFile, Yaml::dump($status));
    }
    
    /**
     * Save job execution history
     * 
     * @return void
     */
    protected function saveJobHistory(): void
    {
        if (!$this->historyPath) {
            return;
        }
        
        $history = [];
        foreach ($this->jobs_run as $job) {
            $history[] = [
                'id' => $job->getId(),
                'executed_at' => date('c'),
                'success' => $job->isSuccessful(),
                'output' => substr($job->getOutput(), 0, 1000),
            ];
        }
        
        if (!empty($history)) {
            $filename = $this->historyPath . '/' . date('Y-m-d') . '.json';
            $existing = file_exists($filename) ? json_decode(file_get_contents($filename), true) : [];
            $existing = array_merge($existing, $history);
            file_put_contents($filename, json_encode($existing, JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Update last run timestamp
     * 
     * @return void
     */
    protected function updateLastRun(): void
    {
        $lastRunFile = $this->status_path . '/last_run.txt';
        file_put_contents($lastRunFile, date('Y-m-d H:i:s'));
    }
    
    /**
     * Get health status
     * 
     * @return array
     */
    public function getHealthStatus(): array
    {
        $lastRunFile = $this->status_path . '/last_run.txt';
        $lastRun = file_exists($lastRunFile) ? file_get_contents($lastRunFile) : null;
        
        // Initialize system jobs if not already done
        $grav = Grav::instance();
        if (count($this->jobs) === 0) {
            // Trigger event to load system jobs (cache-purge, cache-clear, backups, etc.)
            $grav->fireEvent('onSchedulerInitialized', new \RocketTheme\Toolbox\Event\Event(['scheduler' => $this]));
        }
        
        // Load custom jobs
        $this->loadSavedJobs();
        
        // Get only enabled jobs for health status
        [$background, $foreground] = $this->getQueuedJobs(false);
        $enabledJobs = array_merge($background, $foreground);
        
        $now = new DateTime('now');
        $dueJobs = 0;
        
        foreach ($enabledJobs as $job) {
            if ($job->isDue($now)) {
                $dueJobs++;
            }
        }
        
        $health = [
            'status' => 'healthy',
            'last_run' => $lastRun,
            'last_run_age' => null,
            'queue_size' => 0,
            'failed_jobs_24h' => 0,
            'scheduled_jobs' => count($enabledJobs),
            'jobs_due' => $dueJobs,
            'webhook_enabled' => $this->webhookEnabled,
            'health_check_enabled' => $this->healthEnabled,
            'timestamp' => date('c'),
        ];
        
        // Calculate last run age
        if ($lastRun) {
            $lastRunTime = new DateTime($lastRun);
            $health['last_run_age'] = $now->getTimestamp() - $lastRunTime->getTimestamp();
        }
        
        // Determine status based on whether jobs are due
        if ($dueJobs > 0) {
            // Jobs are due but haven't been run
            if ($health['last_run_age'] === null || $health['last_run_age'] > 300) { // No run or older than 5 minutes
                $health['status'] = 'warning';
                $health['message'] = $dueJobs . ' job(s) are due to run';
            }
        } else {
            // No jobs are due - this is healthy
            $health['status'] = 'healthy';
            $health['message'] = 'No jobs currently due';
        }
        
        // Add queue stats if available
        if ($this->jobQueue) {
            $stats = $this->jobQueue->getStatistics();
            $health['queue_size'] = $stats['pending'] ?? 0;
            $health['failed_jobs_24h'] = $stats['failed'] ?? 0;
        }
        
        return $health;
    }
    
    /**
     * Process webhook trigger
     * 
     * @param string|null $token
     * @param string|null $jobId
     * @return array
     */
    public function processWebhookTrigger($token = null, $jobId = null): array
    {
        if (!$this->webhookEnabled) {
            return ['success' => false, 'message' => 'Webhook triggers are not enabled'];
        }
        
        if ($this->webhookToken && $token !== $this->webhookToken) {
            return ['success' => false, 'message' => 'Invalid webhook token'];
        }
        
        // Initialize system jobs if not already done
        $grav = Grav::instance();
        if (count($this->jobs) === 0) {
            // Trigger event to load system jobs (cache-purge, cache-clear, backups, etc.)
            $grav->fireEvent('onSchedulerInitialized', new \RocketTheme\Toolbox\Event\Event(['scheduler' => $this]));
        }
        
        // Load custom jobs
        $this->loadSavedJobs();
        
        if ($jobId) {
            // Force run specific job
            $job = $this->getJob($jobId);
            if ($job) {
                $job->inForeground()->run();
                $this->jobs_run[] = $job;
                $this->saveJobStates();
                $this->updateLastRun();
                
                return [
                    'success' => $job->isSuccessful(),
                    'message' => $job->isSuccessful() ? 'Job force-executed successfully' : 'Job execution failed',
                    'job_id' => $jobId,
                    'forced' => true,
                    'output' => $job->getOutput(),
                ];
            } else {
                return ['success' => false, 'message' => 'Job not found: ' . $jobId];
            }
        } else {
            // Run all due jobs
            $this->run();
            
            return [
                'success' => true,
                'message' => 'Scheduler executed (due jobs only)',
                'jobs_run' => count($this->jobs_run),
                'timestamp' => date('c'),
            ];
        }
    }
}
