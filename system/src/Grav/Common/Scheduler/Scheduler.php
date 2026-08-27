<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use DateTime;
use Grav\Common\Config\Setup;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use InvalidArgumentException;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
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
    /** Run only the jobs whose schedule matches the moment of the run. This is what cron does. */
    public const RUN_DUE = 'due';

    /** Run the due jobs plus any that have missed the last slot their schedule gave them. */
    public const RUN_OVERDUE = 'overdue';

    /** Run every enabled job, whatever its schedule says. */
    public const RUN_ALL = 'all';

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

    /** @var string What started the current run: 'cron' for an automated trigger, 'manual' for a person. */
    protected $runTrigger = 'cron';

    /** @var bool Whether the system jobs have been registered. */
    protected $initialized = false;
    
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
     * Register system jobs (cache maintenance, backups, plugin jobs) if not already done.
     *
     * This used to run in the middleware chain on every request; now it runs only when
     * the scheduler is actually used (CLI run, webhook, health check or job listing).
     *
     * @return void
     */
    public function initializeJobs(): void
    {
        // Tracked with its own flag rather than by asking whether any job is queued yet. A site
        // with a custom job of its own already had one queued by loadSavedJobs() before this
        // ran, so the old test concluded the system jobs were registered when none of them
        // were, and getJob() could not find cache-purge, the backup, or anything a plugin adds.
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $grav = Grav::instance();

        // Make sure the backups listener is registered before the event fires.
        if (isset($grav['backups'])) {
            $grav['backups']->init();
        }

        // Trigger event to load system jobs (cache-purge, cache-clear, backups, etc.)
        $grav->fireEvent('onSchedulerInitialized', new \RocketTheme\Toolbox\Event\Event(['scheduler' => $this]));
    }

    /**
     * Get the queued jobs as background/foreground
     *
     * @param bool $all
     * @return array
     */
    public function getQueuedJobs($all = false)
    {
        $this->initializeJobs();

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
     * @param bool $force Force every enabled job to run. Kept for backwards compatibility; same as $mode = self::RUN_ALL
     * @param string $mode One of self::RUN_DUE, self::RUN_OVERDUE or self::RUN_ALL
     */
    public function run(?DateTime $runTime = null, $force = false, string $mode = self::RUN_DUE)
    {
        // run($time, true) has always meant "run everything", so keep honouring it.
        if ($force) {
            $mode = self::RUN_ALL;
        }

        $this->initializeJobs();


        $this->loadSavedJobs();

        [$background, $foreground] = $this->getQueuedJobs(false);
        $alljobs = array_merge($background, $foreground);

        if (null === $runTime) {
            $runTime = new DateTime('now');
        }

        $this->jobs_run = [];
        $states = (array) $this->getJobStates()->content();

        // Log scheduler run
        if ($this->logger) {
            $jobCount = count($alljobs);
            $forceStr = $mode === self::RUN_DUE ? '' : " ({$mode})";
            $this->logger->debug("Scheduler run started - {$jobCount} jobs available{$forceStr}", [
                'time' => $runTime->format('Y-m-d H:i:s')
            ]);
        }

        // Process jobs based on modern features
        if ($this->jobQueue && ($this->modernConfig['queue']['enabled'] ?? false)) {
            // Queue jobs for processing
            $queuedCount = 0;
            foreach ($alljobs as $job) {
                if ($this->shouldRun($job, $runTime, $mode, $states)) {
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
                if ($this->shouldRun($job, $runTime, $mode, $states)) {
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

        // Store run date. Resolved through the log stream rather than written relative to
        // the current working directory, which is only GRAV_ROOT when the trigger happens
        // to be the generated crontab line (it prefixes `cd <GRAV_ROOT>;`). A webhook, an
        // API call or `bin/grav scheduler` started from elsewhere used to drop the marker
        // somewhere else, or not at all.
        //
        // A run somebody started by hand is deliberately left out: it is not evidence that
        // anything triggers the scheduler on its own, and counting it would make every site
        // report a working cron the moment its owner pressed "Run now".
        if ($this->runTrigger !== 'manual') {
            $lastCron = Grav::instance()['locator']->findResource('log://lastcron.run', true, true);
            file_put_contents($lastCron, (new DateTime('now'))->format('Y-m-d H:i:s'), LOCK_EX);
        }

        // Update last run timestamp for health checks
        $this->updateLastRun();
    }

    /**
     * Whether this job should run in the current pass.
     *
     * @param Job $job
     * @param DateTime $runTime
     * @param string $mode One of self::RUN_DUE, self::RUN_OVERDUE or self::RUN_ALL
     * @param array $states Job states, as stored in status.yaml
     * @return bool
     */
    private function shouldRun(Job $job, DateTime $runTime, string $mode, array $states): bool
    {
        if ($mode === self::RUN_ALL) {
            return true;
        }

        if ($job->isDue($runTime)) {
            return true;
        }

        return $mode === self::RUN_OVERDUE && $this->isOverdue($job, $runTime, $states);
    }

    /**
     * Whether a job has missed the last slot its schedule gave it.
     *
     * A job is only "due" during the minute its cron expression names, which is fine when
     * something runs the scheduler every minute and useless when nothing does. This asks the
     * other question instead: has this job run since the last time it was supposed to? That is
     * what a catch-up run needs to know, and it is the same test isTriggeredExternally() uses
     * to decide whether the jobs are keeping up.
     *
     * @param Job $job
     * @param DateTime|null $runTime
     * @param array|null $states Job states, as stored in status.yaml. Loaded when not supplied
     * @return bool
     */
    public function isOverdue(Job $job, ?DateTime $runTime = null, ?array $states = null): bool
    {
        $expression = $job->getCronExpression();
        if (!$expression) {
            return false;
        }

        $runTime ??= new DateTime('now');
        $states ??= (array) $this->getJobStates()->content();

        try {
            // Include the current date so a job due this very minute still counts.
            $due = $expression->getPreviousRunDate($runTime, 0, true)->getTimestamp();
        } catch (\Exception $e) {
            return false;
        }

        $lastRun = $states[$job->getId()]['last-run'] ?? null;

        // Never run at all: every slot its schedule has had so far was missed.
        return null === $lastRun || $lastRun < $due;
    }

    /**
     * Run a single job by id, in the foreground, recording its state as a normal run would.
     *
     * @param string $jobId
     * @return Job|null The job that ran, or null when nothing has that id
     */
    public function runJob(string $jobId): ?Job
    {
        $this->initializeJobs();
        $this->loadSavedJobs();

        $job = $this->getJob($jobId);
        if (!$job) {
            return null;
        }

        $this->jobs_run = [$job];

        $job->inForeground()->run();

        $this->saveJobStates();
        $this->updateLastRun();

        return $job;
    }

    /**
     * The jobs executed by the last run, in the order they ran.
     *
     * @return Job[]
     */
    public function getJobsRun()
    {
        return $this->jobs_run;
    }

    /**
     * What is starting the run: 'cron' for anything automated, 'manual' for a person pressing a
     * button or typing a command. Manual runs are recorded separately so they cannot be mistaken
     * for a working trigger.
     *
     * @param string $trigger
     * @return $this
     */
    public function setRunTrigger(string $trigger)
    {
        $this->runTrigger = $trigger === 'manual' ? 'manual' : 'cron';

        return $this;
    }

    /**
     * @return string
     */
    public function getRunTrigger(): string
    {
        return $this->runTrigger;
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
        $this->jobs_run = [];
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
        return match ($type) {
            'text' => implode("\n", $this->output_schedule),
            'html' => implode('<br>', $this->output_schedule),
            'array' => $this->output_schedule,
            default => throw new InvalidArgumentException('Invalid output type'),
        };
    }

    /**
     * Remove all queued Jobs.
     *
     * @return $this
     */
    public function clearJobs()
    {
        $this->jobs = [];
        $this->initialized = false;

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
     * @param bool $withEnvironment Append --env=<name> when the current environment has its own overrides
     * @return string
     */
    public function getSchedulerCommand($php = null, bool $withEnvironment = true)
    {
        $phpBinaryFinder = new PhpExecutableFinder();
        $php ??= $phpBinaryFinder->find();
        $command = 'cd ' . str_replace(' ', '\ ', GRAV_ROOT) . ';' . $php . ' bin/grav scheduler';

        // With nothing pinning the environment the CLI resolves to 'cli', so a cron line generated
        // for a site whose environment carries its own overrides (user/env/<host>/config) has to
        // name that environment, or everything defined only there silently never runs (#4248).
        $environment = $withEnvironment ? $this->getOverrideEnvironment() : null;
        if (null !== $environment) {
            $command .= ' --env=' . $environment;
        }

        return $command;
    }

    /**
     * The environment this process booted with, but only when it has its own configuration
     * overrides on disk. Null for the bare CLI ('cli'), for an unknown environment, and for
     * hosts without a user/env/<host>/config folder.
     *
     * @return string|null
     */
    public function getOverrideEnvironment(): ?string
    {
        $environment = Setup::$environment;
        if (!is_string($environment) || in_array($environment, ['', 'cli', 'unknown'], true)) {
            return null;
        }

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $locator->findResource('environment://config', true) ? $environment : null;
    }

    /**
     * Environment the scheduler last ran under: 'cli' for an unpinned crontab, the hostname for
     * a webhook or admin-triggered run. Null until a run has happened on this version.
     *
     * @return string|null
     */
    public function getLastRunEnvironment(): ?string
    {
        $file = $this->status_path . '/last_run_environment.txt';
        if (!is_file($file)) {
            return null;
        }

        $environment = trim((string) file_get_contents($file));

        return $environment !== '' ? $environment : null;
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
        // Trigger-agnostic check first. This replaces a fixed 120-second window that
        // reported "not set up" for anyone running the trigger on anything other than an
        // every-minute crontab.
        if ($this->isTriggeredExternally()) {
            return 1;
        }

        // No external trigger evidence, so fall back to inspecting the crontab. Hosts that
        // disable proc_open cannot run this at all -- Symfony's Process throws straight from
        // its constructor -- so report "unable to determine" rather than fataling.
        if (!static::isProcessAvailable()) {
            return 2;
        }

        $process = new Process(['crontab', '-l']);
        $process->run();

        if ($process->isSuccessful()) {
            $output = $process->getOutput();

            // Recognise any crontab line that actually runs the scheduler, not only the
            // exact string getCronCommand() emits. A hand-written entry -- or one written
            // by a Docker image or a control panel -- is commonly spelled `&&` rather than
            // `;`, spaced differently, or given an absolute path to bin/grav with no `cd`
            // at all, and every one of those works fine while the old exact match reported
            // "cron is not set up". Anchored on the repository root plus `bin/grav
            // scheduler` so another site's entry in the same crontab still does not count.
            $root = preg_quote(rtrim(GRAV_ROOT, '/'), '/');
            $full_command = '/^(?!#).*\s' . $root . '(?:\/|\\\\ |;|\s|&).*bin\/grav\s+scheduler\b/m';

            if (preg_match($full_command, $output)) {
                return 1;
            }

            // Also accept an absolute `<root>/bin/grav scheduler` with no `cd` prefix.
            return preg_match('/^(?!#).*\s' . $root . '\/bin\/grav\s+scheduler\b/m', $output) ? 1 : 0;
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
                $new_states[$job->getId()] = ['state' => 'success', 'last-run' => $now, 'trigger' => $this->runTrigger];
                $this->pushExecutedJob($job);
            } else {
                $new_states[$job->getId()] = ['state' => 'failure', 'last-run' => $now, 'trigger' => $this->runTrigger, 'error' => $job->getOutput()];
                $this->pushFailedJob($job);
            }
        }

        $saved_states = $this->getJobStates();
        $saved_states->save(array_merge($saved_states->content(), $new_states));
    }

    /**
     * Whether this PHP installation can start external processes at all.
     *
     * Shared hosts routinely disable proc_open, which is all Symfony's Process needs to
     * throw -- and it throws from the constructor, so merely building one is fatal.
     *
     * @return bool
     */
    public static function isProcessAvailable(): bool
    {
        return function_exists('proc_open');
    }

    /**
     * Timestamp of the last scheduler run, whichever trigger ran it.
     *
     * @return int|null
     */
    public function getLastRun(): ?int
    {
        $candidates = [
            $this->status_path . '/last_run.txt',
            Grav::instance()['locator']->findResource('log://lastcron.run'),
        ];

        foreach ($candidates as $file) {
            if ($file && is_file($file)) {
                $stamp = strtotime(trim((string) file_get_contents($file)));
                if ($stamp) {
                    return $stamp;
                }
            }
        }

        return null;
    }

    /**
     * Whether the scheduler is evidently being triggered from outside, whatever the
     * trigger happens to be: crontab, webhook, an API call, or a scheduled task on
     * Windows.
     *
     * Rather than requiring a run inside a fixed window, this asks each enabled job
     * whether it has actually run since the last moment its own schedule said it should
     * have. A site whose trigger fires twice a day passes exactly as one that fires every
     * minute, provided the jobs keep up -- and a broken every-minute trigger is still
     * caught within the minute rather than hidden for a day.
     *
     * @return bool
     */
    public function isTriggeredExternally(): bool
    {
        try {
            $states = (array) $this->getJobStates()->content();
            $jobs = array_filter($this->getAllJobs(), static function ($job) {
                return $job->getEnabled();
            });
        } catch (\Throwable $e) {
            return false;
        }

        if (!$jobs) {
            // Nothing scheduled, so nothing can be behind. Fall back to the scheduler's
            // own marker, if one was ever written.
            return null !== $this->getLastRun();
        }

        $grace = (int) ($this->modernConfig['trigger_grace'] ?? 300);

        foreach ($jobs as $job) {
            $expression = $job->getCronExpression();
            if (!$expression) {
                continue;
            }

            $state = $states[$job->getId()] ?? [];

            // A job somebody ran by hand proves nothing about whether the scheduler is being
            // triggered on its own, so it counts as never having run here.
            $lastRun = ($state['trigger'] ?? 'cron') === 'manual' ? null : ($state['last-run'] ?? null);
            if (null === $lastRun) {
                return false;
            }

            try {
                $due = $expression->getPreviousRunDate('now', 0, true)->getTimestamp();
            } catch (\Exception $e) {
                continue;
            }

            if ($lastRun + $grace < $due) {
                return false;
            }
        }

        return true;
    }

    /**
     * How the cron status was arrived at: 'last-run', 'crontab' or 'unavailable'.
     *
     * @return string
     */
    public function getCronDetectionMethod(): string
    {
        if ($this->isTriggeredExternally()) {
            return 'last-run';
        }

        return static::isProcessAvailable() ? 'crontab' : 'unavailable';
    }

    /**
     * Try to determine who's running the process
     *
     * @return false|string
     */
    public function whoami()
    {
        if (!static::isProcessAvailable()) {
            // proc_open is disabled, so answer from what PHP itself knows rather than
            // taking the whole request down over a cosmetic detail.
            if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
                $info = @posix_getpwuid(posix_geteuid());
                if (!empty($info['name'])) {
                    return $info['name'];
                }
            }

            return $_SERVER['USER'] ?? (get_current_user() ?: 'unknown');
        }

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
     * @return void
     */
    protected function initializeModernFeatures(mixed $locator): void
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
        // Requires both: the config toggle enabled AND the scheduler-webhook plugin installed
        if (!$this->webhookEnabled) {
            return false;
        }

        $grav = Grav::instance();
        return (bool) $grav['config']->get('plugins.scheduler-webhook.enabled', false);
    }

    /**
     * Check if the scheduler-webhook plugin is installed and enabled
     *
     * @return bool
     */
    public function isWebhookPluginReady(): bool
    {
        $grav = Grav::instance();
        return (bool) $grav['config']->get('plugins.scheduler-webhook.enabled', false);
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
        // Registering the same id twice -- two callers firing onSchedulerInitialized, say --
        // must not give the job two entries and two runs. First registration wins.
        foreach ($this->jobs as $existing) {
            if ($existing->getId() === $job->getId()) {
                return;
            }
        }

        $this->jobs[] = $job;
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
        $output = trim((string) $job->getOutput());
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
                                $error = trim((string) $job->getOutput()) ?: 'Unknown error';
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
                $error = trim((string) $job->getOutput()) ?: 'Unknown error';
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
            'trigger' => $this->runTrigger,
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
                'output' => substr((string) $job->getOutput(), 0, 1000),
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
        // A run started by hand gets its own marker. It is worth showing -- "you last ran this
        // yourself two minutes ago" is useful -- but it must not be mistaken for the scheduler
        // being triggered automatically, and it must not overwrite the environment the real
        // trigger last ran under.
        if ($this->runTrigger === 'manual') {
            file_put_contents($this->status_path . '/last_manual_run.txt', date('Y-m-d H:i:s'));

            return;
        }

        $lastRunFile = $this->status_path . '/last_run.txt';
        file_put_contents($lastRunFile, date('Y-m-d H:i:s'));

        // Remember which environment this run loaded its configuration from, so the admin can
        // tell when the cron runs under a different one than the site itself (#4248).
        file_put_contents($this->status_path . '/last_run_environment.txt', (string) Setup::$environment);
    }

    /**
     * Timestamp of the last run somebody started by hand, from the admin or the command line.
     *
     * @return int|null
     */
    public function getLastManualRun(): ?int
    {
        $file = $this->status_path . '/last_manual_run.txt';
        if (!is_file($file)) {
            return null;
        }

        $stamp = strtotime(trim((string) file_get_contents($file)));

        return $stamp ?: null;
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

        $this->initializeJobs();

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
            'webhook_enabled' => $this->isWebhookEnabled(),
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

        // Fail closed. An enabled webhook with no configured token must not run
        // jobs for anonymous callers: require a token to be set, and require the
        // caller to match it with a timing-safe comparison. A previous compound
        // check short-circuited when the token was null, letting an unconfigured
        // token accept every request. (GHSA-xwv3-2mv2-w33x / CVE-2026-57852)
        if (!is_string($this->webhookToken) || $this->webhookToken === '') {
            return ['success' => false, 'message' => 'Webhook token is not configured'];
        }

        if (!is_string($token) || !hash_equals($this->webhookToken, $token)) {
            return ['success' => false, 'message' => 'Invalid webhook token'];
        }

        $this->initializeJobs();

        // Load custom jobs
        $this->loadSavedJobs();

        if ($jobId) {
            // Force run specific job
            $job = $this->runJob($jobId);
            if ($job) {
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
