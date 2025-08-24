<?php

/**
 * @package    Grav\Common\Scheduler
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use DateTime;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\File\JsonFile;
use Symfony\Component\Process\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Modern Scheduler with enhanced features for reliability and monitoring
 * 
 * @package Grav\Common\Scheduler
 */
class ModernScheduler extends Scheduler
{
    /** @var array */
    protected $workers = [];
    
    /** @var int */
    protected $maxWorkers = 1;
    
    /** @var string */
    protected $queuePath;
    
    /** @var string */
    protected $historyPath;
    
    /** @var array */
    protected $modernConfig;
    
    /** @var bool */
    protected $webhookEnabled = false;
    
    /** @var string|null */
    protected $webhookToken;
    
    /** @var bool */
    protected $healthEnabled = true;
    
    /** @var JobQueue */
    protected $jobQueue;
    
    /**
     * Create new ModernScheduler instance
     */
    public function __construct()
    {
        parent::__construct();
        
        $grav = Grav::instance();
        $this->modernConfig = $grav['config']->get('scheduler.modern', []);
        
        // Set up modern features if enabled
        if ($this->isModernEnabled()) {
            $this->initializeModernFeatures();
        }
    }
    
    /**
     * Check if modern features are enabled
     * 
     * @return bool
     */
    public function isModernEnabled(): bool
    {
        return $this->modernConfig['enabled'] ?? false;
    }
    
    /**
     * Initialize modern scheduler features
     * 
     * @return void
     */
    protected function initializeModernFeatures(): void
    {
        $locator = Grav::instance()['locator'];
        
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
        
        // Initialize job queue
        $this->jobQueue = new JobQueue($this->queuePath);
        
        // Configure workers
        $this->maxWorkers = $this->modernConfig['workers'] ?? 1;
        
        // Configure webhook
        $this->webhookEnabled = $this->modernConfig['webhook']['enabled'] ?? false;
        $this->webhookToken = $this->modernConfig['webhook']['token'] ?? null;
        
        // Configure health check
        $this->healthEnabled = $this->modernConfig['health']['enabled'] ?? true;
    }
    
    /**
     * Enhanced run method with modern features
     * 
     * @param DateTime|null $runTime
     * @param bool $force
     * @return void
     */
    public function run(DateTime $runTime = null, $force = false): void
    {
        if (!$this->isModernEnabled()) {
            // Fall back to parent implementation
            parent::run($runTime, $force);
            return;
        }
        
        $this->loadSavedJobs();
        
        if (null === $runTime) {
            $runTime = new DateTime('now');
        }
        
        // Process queued jobs first
        $this->processQueuedJobs();
        
        // Get scheduled jobs
        [$background, $foreground] = $this->getQueuedJobs(false);
        $alljobs = array_merge($background, $foreground);
        
        // Check which jobs are due and add them to the queue
        foreach ($alljobs as $job) {
            if ($job->isDue($runTime) || $force) {
                if ($job instanceof ModernJob) {
                    // Add to queue for processing
                    $this->jobQueue->push($job);
                } else {
                    // Run legacy jobs directly
                    $job->run();
                    $this->jobs_run[] = $job;
                }
            }
        }
        
        // Process jobs with workers
        $this->processJobsWithWorkers();
        
        // Store states and history
        $this->saveJobStates();
        $this->saveJobHistory();
        
        // Update last run timestamp
        $this->updateLastRun();
    }
    
    /**
     * Process jobs from the queue
     * 
     * @return void
     */
    protected function processQueuedJobs(): void
    {
        $maxSize = $this->modernConfig['queue']['max_size'] ?? 1000;
        
        while (!$this->jobQueue->isEmpty() && count($this->workers) < $this->maxWorkers) {
            $job = $this->jobQueue->pop();
            
            if ($job) {
                $this->executeJob($job);
            }
        }
    }
    
    /**
     * Process jobs using multiple workers
     * 
     * @return void
     */
    protected function processJobsWithWorkers(): void
    {
        // Wait for all workers to complete
        foreach ($this->workers as $workerId => $process) {
            if ($process instanceof Process) {
                $process->wait();
                unset($this->workers[$workerId]);
            }
        }
    }
    
    /**
     * Execute a job with retry support
     * 
     * @param Job $job
     * @return void
     */
    protected function executeJob(Job $job): void
    {
        if ($job instanceof ModernJob) {
            // Use modern job execution with retry
            $job->runWithRetry();
        } else {
            // Use standard job execution
            $job->run();
        }
        
        $this->jobs_run[] = $job;
        
        // Handle background jobs
        if ($job->runInBackground() && $this->maxWorkers > 1) {
            $process = $job->getProcess();
            if ($process) {
                $this->workers[] = $process;
            }
        }
    }
    
    /**
     * Save job execution history
     * 
     * @return void
     */
    protected function saveJobHistory(): void
    {
        if (!$this->modernConfig['history']['enabled'] ?? true) {
            return;
        }
        
        $now = new DateTime('now');
        $historyFile = $this->historyPath . '/' . $now->format('Y-m-d') . '.json';
        
        $history = [];
        if (file_exists($historyFile)) {
            $file = JsonFile::instance($historyFile);
            $history = $file->content();
        } else {
            $file = JsonFile::instance($historyFile);
        }
        
        foreach ($this->jobs_run as $job) {
            $entry = [
                'job_id' => $job->getId(),
                'command' => is_string($job->getCommand()) ? $job->getCommand() : 'Closure',
                'timestamp' => $now->format('c'),
                'success' => $job->isSuccessful(),
                'duration' => $job instanceof ModernJob ? $job->getExecutionTime() : null,
                'output' => substr($job->getOutput(), 0, 1000), // Limit output size
                'retry_count' => $job instanceof ModernJob ? $job->getRetryCount() : 0,
            ];
            
            $history[] = $entry;
        }
        
        $file->save($history);
        
        // Clean up old history files
        $this->cleanupHistory();
    }
    
    /**
     * Clean up old history files
     * 
     * @return void
     */
    protected function cleanupHistory(): void
    {
        $retentionDays = $this->modernConfig['history']['retention_days'] ?? 30;
        $cutoffDate = new DateTime("-{$retentionDays} days");
        
        $files = glob($this->historyPath . '/*.json');
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            $fileDate = DateTime::createFromFormat('Y-m-d', $filename);
            
            if ($fileDate && $fileDate < $cutoffDate) {
                unlink($file);
            }
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
        file_put_contents($lastRunFile, (new DateTime('now'))->format('c'), LOCK_EX);
        
        // Also update the legacy location for backward compatibility
        file_put_contents('logs/lastcron.run', (new DateTime('now'))->format('Y-m-d H:i:s'), LOCK_EX);
    }
    
    /**
     * Check scheduler health
     * 
     * @return array
     */
    public function getHealthStatus(): array
    {
        $lastRunFile = $this->status_path . '/last_run.txt';
        $lastRun = file_exists($lastRunFile) ? file_get_contents($lastRunFile) : null;
        
        $health = [
            'status' => 'healthy',
            'last_run' => $lastRun,
            'last_run_age' => null,
            'queue_size' => 0,
            'failed_jobs_24h' => 0,
            'scheduled_jobs' => count($this->getAllJobs()),
            'modern_features' => $this->isModernEnabled(),
            'workers' => $this->maxWorkers,
            'trigger_methods' => $this->getActiveTriggers(),
        ];
        
        if ($lastRun) {
            $lastRunTime = new DateTime($lastRun);
            $now = new DateTime('now');
            $diff = $now->getTimestamp() - $lastRunTime->getTimestamp();
            $health['last_run_age'] = $diff;
            
            // Mark as unhealthy if no run in last 10 minutes
            if ($diff > 600) {
                $health['status'] = 'warning';
            }
            
            // Mark as critical if no run in last hour
            if ($diff > 3600) {
                $health['status'] = 'critical';
            }
        } else {
            $health['status'] = 'unknown';
        }
        
        // Get queue size if modern features enabled
        if ($this->isModernEnabled() && $this->jobQueue) {
            $health['queue_size'] = $this->jobQueue->size();
        }
        
        // Count failed jobs in last 24 hours
        $health['failed_jobs_24h'] = $this->countRecentFailures();
        
        return $health;
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
        
        // Check cron
        $cronStatus = $this->isCrontabSetup();
        if ($cronStatus === 1) {
            $triggers[] = 'cron';
        }
        
        // Check systemd timer
        if ($this->isSystemdTimerActive()) {
            $triggers[] = 'systemd';
        }
        
        // Check webhook
        if ($this->webhookEnabled) {
            $triggers[] = 'webhook';
        }
        
        // Check for external triggers
        $lastRunFile = $this->status_path . '/last_run.txt';
        if (file_exists($lastRunFile)) {
            $lastRun = file_get_contents($lastRunFile);
            $lastRunTime = new DateTime($lastRun);
            $now = new DateTime('now');
            $diff = $now->getTimestamp() - $lastRunTime->getTimestamp();
            
            if ($diff < 120) {
                $triggers[] = 'external';
            }
        }
        
        return $triggers;
    }
    
    /**
     * Check if systemd timer is active
     * 
     * @return bool
     */
    protected function isSystemdTimerActive(): bool
    {
        if (Utils::isWindows()) {
            return false;
        }
        
        $process = new Process(['systemctl', 'is-active', 'grav-scheduler.timer']);
        $process->run();
        
        return $process->isSuccessful() && trim($process->getOutput()) === 'active';
    }
    
    /**
     * Count recent job failures
     * 
     * @return int
     */
    protected function countRecentFailures(): int
    {
        $count = 0;
        $cutoff = new DateTime('-24 hours');
        
        // Check today's history
        $todayFile = $this->historyPath . '/' . date('Y-m-d') . '.json';
        if (file_exists($todayFile)) {
            $file = JsonFile::instance($todayFile);
            $history = $file->content();
            
            foreach ($history as $entry) {
                $entryTime = new DateTime($entry['timestamp']);
                if ($entryTime > $cutoff && !$entry['success']) {
                    $count++;
                }
            }
        }
        
        // Check yesterday's history
        $yesterdayFile = $this->historyPath . '/' . $cutoff->format('Y-m-d') . '.json';
        if (file_exists($yesterdayFile)) {
            $file = JsonFile::instance($yesterdayFile);
            $history = $file->content();
            
            foreach ($history as $entry) {
                $entryTime = new DateTime($entry['timestamp']);
                if ($entryTime > $cutoff && !$entry['success']) {
                    $count++;
                }
            }
        }
        
        return $count;
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
        
        if ($jobId) {
            // Force run specific job (manual override - ignore schedule)
            $job = $this->getJob($jobId);
            if ($job) {
                // Force run in foreground to get immediate result
                $job->inForeground()->run();
                
                // Track as manually executed
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
            // Run all due jobs normally
            $this->run();
            return [
                'success' => true,
                'message' => 'Scheduler executed (due jobs only)',
                'jobs_run' => count($this->jobs_run),
                'timestamp' => date('c'),
            ];
        }
    }
    
    /**
     * Get scheduler statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_jobs' => count($this->getAllJobs()),
            'enabled_jobs' => 0,
            'disabled_jobs' => 0,
            'executions_today' => 0,
            'failures_today' => 0,
            'average_execution_time' => 0,
            'queue_size' => 0,
        ];
        
        // Count enabled/disabled jobs
        foreach ($this->getAllJobs() as $job) {
            if ($job->getEnabled()) {
                $stats['enabled_jobs']++;
            } else {
                $stats['disabled_jobs']++;
            }
        }
        
        // Get today's statistics
        $todayFile = $this->historyPath . '/' . date('Y-m-d') . '.json';
        if (file_exists($todayFile)) {
            $file = JsonFile::instance($todayFile);
            $history = $file->content();
            
            $totalTime = 0;
            $timeCount = 0;
            
            foreach ($history as $entry) {
                $stats['executions_today']++;
                
                if (!$entry['success']) {
                    $stats['failures_today']++;
                }
                
                if (isset($entry['duration']) && $entry['duration'] > 0) {
                    $totalTime += $entry['duration'];
                    $timeCount++;
                }
            }
            
            if ($timeCount > 0) {
                $stats['average_execution_time'] = round($totalTime / $timeCount, 2);
            }
        }
        
        // Get queue size
        if ($this->isModernEnabled() && $this->jobQueue) {
            $stats['queue_size'] = $this->jobQueue->size();
        }
        
        return $stats;
    }
    
    /**
     * Run scheduler in daemon mode
     * 
     * @param int $interval Check interval in seconds (default: 60)
     * @return void
     */
    public function runDaemon($interval = 60): void
    {
        if (!$this->isModernEnabled()) {
            throw new RuntimeException('Daemon mode requires modern features to be enabled');
        }
        
        $lastRun = 0;
        
        while (true) {
            $now = time();
            
            // Run scheduler every minute
            if ($now - $lastRun >= $interval) {
                $this->run();
                $lastRun = $now;
            }
            
            // Process any queued jobs
            $this->processQueuedJobs();
            
            // Sleep for a short interval
            sleep(5);
            
            // Check for shutdown signal
            if (file_exists($this->status_path . '/shutdown')) {
                unlink($this->status_path . '/shutdown');
                break;
            }
        }
    }
}