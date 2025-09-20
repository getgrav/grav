<?php

/**
 * @package    Grav\Common\Scheduler
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use RocketTheme\Toolbox\File\JsonFile;
use RuntimeException;

/**
 * File-based job queue implementation
 * 
 * @package Grav\Common\Scheduler
 */
class JobQueue
{
    /** @var string */
    protected $queuePath;
    
    /** @var string */
    protected $lockFile;
    
    /** @var array Priority levels */
    const PRIORITY_HIGH = 'high';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_LOW = 'low';
    
    /**
     * JobQueue constructor
     * 
     * @param string $queuePath
     */
    public function __construct(string $queuePath)
    {
        $this->queuePath = $queuePath;
        $this->lockFile = $queuePath . '/.lock';
        
        // Create queue directories
        $this->initializeDirectories();
    }
    
    /**
     * Initialize queue directories
     * 
     * @return void
     */
    protected function initializeDirectories(): void
    {
        $dirs = [
            $this->queuePath . '/pending',
            $this->queuePath . '/processing',
            $this->queuePath . '/failed',
            $this->queuePath . '/completed',
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Push a job to the queue
     * 
     * @param Job $job
     * @param string $priority
     * @return string Job queue ID
     */
    public function push(Job $job, string $priority = self::PRIORITY_NORMAL): string
    {
        $queueId = $this->generateQueueId($job);
        $timestamp = microtime(true);
        
        $queueItem = [
            'id' => $queueId,
            'job_id' => $job->getId(),
            'command' => is_string($job->getCommand()) ? $job->getCommand() : 'Closure',
            'arguments' => method_exists($job, 'getRawArguments') ? $job->getRawArguments() : $job->getArguments(),
            'priority' => $priority,
            'timestamp' => $timestamp,
            'attempts' => 0,
            'max_attempts' => method_exists($job, 'getMaxAttempts') ? $job->getMaxAttempts() : 1,
            'created_at' => date('c'),
            'scheduled_for' => null,
            'metadata' => [],
        ];
        
        // Always serialize the job to preserve its full state
        $queueItem['serialized_job'] = base64_encode(serialize($job));
        
        $this->writeQueueItem($queueItem, 'pending');
        
        return $queueId;
    }
    
    /**
     * Push a job for delayed execution
     * 
     * @param Job $job
     * @param \DateTime $scheduledFor
     * @param string $priority
     * @return string
     */
    public function pushDelayed(Job $job, \DateTime $scheduledFor, string $priority = self::PRIORITY_NORMAL): string
    {
        $queueId = $this->push($job, $priority);
        
        // Update the scheduled time
        $item = $this->getQueueItem($queueId, 'pending');
        if ($item) {
            $item['scheduled_for'] = $scheduledFor->format('c');
            $this->writeQueueItem($item, 'pending');
        }
        
        return $queueId;
    }
    
    /**
     * Pop the next job from the queue
     * 
     * @return Job|null
     */
    public function pop(): ?Job
    {
        if (!$this->lock()) {
            return null;
        }
        
        try {
            // Get all pending items
            $items = $this->getPendingItems();
            
            if (empty($items)) {
                $this->unlock();
                return null;
            }
            
            // Sort by priority and timestamp
            usort($items, function($a, $b) {
                $priorityOrder = [
                    self::PRIORITY_HIGH => 0,
                    self::PRIORITY_NORMAL => 1,
                    self::PRIORITY_LOW => 2,
                ];
                
                $aPriority = $priorityOrder[$a['priority']] ?? 1;
                $bPriority = $priorityOrder[$b['priority']] ?? 1;
                
                if ($aPriority !== $bPriority) {
                    return $aPriority - $bPriority;
                }
                
                return $a['timestamp'] <=> $b['timestamp'];
            });
            
            // Get the first item that's ready to run
            $now = new \DateTime();
            foreach ($items as $item) {
                if ($item['scheduled_for']) {
                    $scheduledTime = new \DateTime($item['scheduled_for']);
                    if ($scheduledTime > $now) {
                        continue; // Skip items not yet due
                    }
                }
                
                // Move to processing
                $this->moveQueueItem($item['id'], 'pending', 'processing');
                
                // Reconstruct the job
                $job = $this->reconstructJob($item);
                
                $this->unlock();
                return $job;
            }
            
            $this->unlock();
            return null;
            
        } catch (\Exception $e) {
            $this->unlock();
            throw $e;
        }
    }
    
    /**
     * Pop a job from the queue with its queue ID
     * 
     * @return array|null Array with 'job' and 'id' keys
     */
    public function popWithId(): ?array
    {
        if (!$this->lock()) {
            return null;
        }
        
        try {
            // Get all pending items
            $items = $this->getPendingItems();
            
            if (empty($items)) {
                $this->unlock();
                return null;
            }
            
            // Sort by priority and timestamp
            usort($items, function($a, $b) {
                $priorityOrder = [
                    self::PRIORITY_HIGH => 0,
                    self::PRIORITY_NORMAL => 1,
                    self::PRIORITY_LOW => 2,
                ];
                
                $aPriority = $priorityOrder[$a['priority']] ?? 1;
                $bPriority = $priorityOrder[$b['priority']] ?? 1;
                
                if ($aPriority !== $bPriority) {
                    return $aPriority - $bPriority;
                }
                
                return $a['timestamp'] <=> $b['timestamp'];
            });
            
            // Get the first item that's ready to run
            $now = new \DateTime();
            foreach ($items as $item) {
                if ($item['scheduled_for']) {
                    $scheduledTime = new \DateTime($item['scheduled_for']);
                    if ($scheduledTime > $now) {
                        continue; // Skip items not yet due
                    }
                }
                
                // Reconstruct the job first before moving it
                $job = $this->reconstructJob($item);
                
                if (!$job) {
                    // Failed to reconstruct, skip this item
                    continue;
                }
                
                // Move to processing only if we can reconstruct the job
                $this->moveQueueItem($item['id'], 'pending', 'processing');
                
                $this->unlock();
                return ['job' => $job, 'id' => $item['id']];
            }
            
            $this->unlock();
            return null;
            
        } catch (\Exception $e) {
            $this->unlock();
            throw $e;
        }
    }
    
    /**
     * Mark a job as completed
     * 
     * @param string $queueId
     * @return void
     */
    public function complete(string $queueId): void
    {
        $this->moveQueueItem($queueId, 'processing', 'completed');
        
        // Clean up old completed items
        $this->cleanupCompleted();
    }
    
    /**
     * Mark a job as failed
     * 
     * @param string $queueId
     * @param string $error
     * @return void
     */
    public function fail(string $queueId, string $error = ''): void
    {
        $item = $this->getQueueItem($queueId, 'processing');
        
        if ($item) {
            $item['attempts']++;
            $item['last_error'] = $error;
            $item['failed_at'] = date('c');
            
            if ($item['attempts'] < $item['max_attempts']) {
                // Move back to pending for retry
                $item['retry_at'] = $this->calculateRetryTime($item['attempts']);
                $item['scheduled_for'] = $item['retry_at'];
                $this->writeQueueItem($item, 'pending');
                $this->deleteQueueItem($queueId, 'processing');
            } else {
                // Move to failed (dead letter queue)
                $this->writeQueueItem($item, 'failed');
                $this->deleteQueueItem($queueId, 'processing');
            }
        }
    }
    
    /**
     * Get queue size
     * 
     * @return int
     */
    public function size(): int
    {
        return count($this->getPendingItems());
    }
    
    /**
     * Check if queue is empty
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }
    
    /**
     * Get queue statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'pending' => count($this->getPendingItems()),
            'processing' => count($this->getItemsInDirectory('processing')),
            'failed' => count($this->getItemsInDirectory('failed')),
            'completed_today' => $this->countCompletedToday(),
        ];
    }
    
    /**
     * Generate a unique queue ID
     * 
     * @param Job $job
     * @return string
     */
    protected function generateQueueId(Job $job): string
    {
        return $job->getId() . '_' . uniqid('', true);
    }
    
    /**
     * Write queue item to disk
     * 
     * @param array $item
     * @param string $directory
     * @return void
     */
    protected function writeQueueItem(array $item, string $directory): void
    {
        $path = $this->queuePath . '/' . $directory . '/' . $item['id'] . '.json';
        $file = JsonFile::instance($path);
        $file->save($item);
    }
    
    /**
     * Read queue item from disk
     * 
     * @param string $queueId
     * @param string $directory
     * @return array|null
     */
    protected function getQueueItem(string $queueId, string $directory): ?array
    {
        $path = $this->queuePath . '/' . $directory . '/' . $queueId . '.json';
        
        if (!file_exists($path)) {
            return null;
        }
        
        $file = JsonFile::instance($path);
        return $file->content();
    }
    
    /**
     * Delete queue item
     * 
     * @param string $queueId
     * @param string $directory
     * @return void
     */
    protected function deleteQueueItem(string $queueId, string $directory): void
    {
        $path = $this->queuePath . '/' . $directory . '/' . $queueId . '.json';
        
        if (file_exists($path)) {
            unlink($path);
        }
    }
    
    /**
     * Move queue item between directories
     * 
     * @param string $queueId
     * @param string $fromDir
     * @param string $toDir
     * @return void
     */
    protected function moveQueueItem(string $queueId, string $fromDir, string $toDir): void
    {
        $fromPath = $this->queuePath . '/' . $fromDir . '/' . $queueId . '.json';
        $toPath = $this->queuePath . '/' . $toDir . '/' . $queueId . '.json';
        
        if (file_exists($fromPath)) {
            rename($fromPath, $toPath);
        }
    }
    
    /**
     * Get all pending items
     * 
     * @return array
     */
    protected function getPendingItems(): array
    {
        return $this->getItemsInDirectory('pending');
    }
    
    /**
     * Get items in a specific directory
     * 
     * @param string $directory
     * @return array
     */
    protected function getItemsInDirectory(string $directory): array
    {
        $items = [];
        $path = $this->queuePath . '/' . $directory;
        
        if (!is_dir($path)) {
            return $items;
        }
        
        $files = glob($path . '/*.json');
        foreach ($files as $file) {
            $jsonFile = JsonFile::instance($file);
            $items[] = $jsonFile->content();
        }
        
        return $items;
    }
    
    /**
     * Reconstruct a job from queue item
     * 
     * @param array $item
     * @return Job|null
     */
    protected function reconstructJob(array $item): ?Job
    {
        if (isset($item['serialized_job'])) {
            // Unserialize the job
            try {
                $job = unserialize(base64_decode($item['serialized_job']));
                if ($job instanceof Job) {
                    return $job;
                }
            } catch (\Exception $e) {
                // Failed to unserialize
                return null;
            }
        }
        
        // Create a new job from command
        if (isset($item['command'])) {
            $args = $item['arguments'] ?? [];
            $job = new Job($item['command'], $args, $item['job_id']);
            return $job;
        }
        
        return null;
    }
    
    /**
     * Calculate retry time with exponential backoff
     * 
     * @param int $attempts
     * @return string
     */
    protected function calculateRetryTime(int $attempts): string
    {
        $backoffSeconds = min(pow(2, $attempts) * 60, 3600); // Max 1 hour
        $retryTime = new \DateTime();
        $retryTime->modify("+{$backoffSeconds} seconds");
        return $retryTime->format('c');
    }
    
    /**
     * Clean up old completed items
     * 
     * @return void
     */
    protected function cleanupCompleted(): void
    {
        $items = $this->getItemsInDirectory('completed');
        $cutoff = new \DateTime('-24 hours');
        
        foreach ($items as $item) {
            if (isset($item['created_at'])) {
                $createdAt = new \DateTime($item['created_at']);
                if ($createdAt < $cutoff) {
                    $this->deleteQueueItem($item['id'], 'completed');
                }
            }
        }
    }
    
    /**
     * Count completed jobs today
     * 
     * @return int
     */
    protected function countCompletedToday(): int
    {
        $items = $this->getItemsInDirectory('completed');
        $today = new \DateTime('today');
        $count = 0;
        
        foreach ($items as $item) {
            if (isset($item['created_at'])) {
                $createdAt = new \DateTime($item['created_at']);
                if ($createdAt >= $today) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Acquire lock for queue operations
     * 
     * @return bool
     */
    protected function lock(): bool
    {
        $attempts = 0;
        $maxAttempts = 50; // 5 seconds total
        
        while ($attempts < $maxAttempts) {
            // Check if lock file exists and is stale (older than 30 seconds)
            if (file_exists($this->lockFile)) {
                $lockAge = time() - filemtime($this->lockFile);
                if ($lockAge > 30) {
                    // Stale lock, remove it
                    @unlink($this->lockFile);
                }
            }
            
            // Try to acquire lock atomically
            $handle = @fopen($this->lockFile, 'x');
            if ($handle !== false) {
                fclose($handle);
                return true;
            }
            
            $attempts++;
            usleep(100000); // 100ms
        }
        
        // Could not acquire lock
        return false;
    }
    
    /**
     * Release queue lock
     * 
     * @return void
     */
    protected function unlock(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
}