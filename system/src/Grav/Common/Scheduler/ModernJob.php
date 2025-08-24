<?php

/**
 * @package    Grav\Common\Scheduler
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use DateTime;
use Exception;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Enhanced Job class with modern features
 * 
 * @package Grav\Common\Scheduler
 */
class ModernJob extends Job
{
    /** @var int */
    protected $maxAttempts = 3;
    
    /** @var int */
    protected $retryCount = 0;
    
    /** @var int */
    protected $retryDelay = 60; // seconds
    
    /** @var string */
    protected $retryStrategy = 'exponential'; // 'linear' or 'exponential'
    
    /** @var float */
    protected $executionStartTime;
    
    /** @var float */
    protected $executionTime = 0;
    
    /** @var int */
    protected $timeout = 300; // 5 minutes default
    
    /** @var array */
    protected $dependencies = [];
    
    /** @var array */
    protected $chainedJobs = [];
    
    /** @var string|null */
    protected $queueId;
    
    /** @var string */
    protected $priority = 'normal'; // 'high', 'normal', 'low'
    
    /** @var array */
    protected $metadata = [];
    
    /** @var array */
    protected $tags = [];
    
    /** @var callable|null */
    protected $onSuccess;
    
    /** @var callable|null */
    protected $onFailure;
    
    /** @var callable|null */
    protected $onRetry;
    
    /**
     * Set maximum retry attempts
     * 
     * @param int $attempts
     * @return self
     */
    public function maxAttempts(int $attempts): self
    {
        $this->maxAttempts = $attempts;
        return $this;
    }
    
    /**
     * Get maximum retry attempts
     * 
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
    
    /**
     * Set retry delay
     * 
     * @param int $seconds
     * @param string $strategy 'linear' or 'exponential'
     * @return self
     */
    public function retryDelay(int $seconds, string $strategy = 'exponential'): self
    {
        $this->retryDelay = $seconds;
        $this->retryStrategy = $strategy;
        return $this;
    }
    
    /**
     * Get current retry count
     * 
     * @return int
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }
    
    /**
     * Set job timeout
     * 
     * @param int $seconds
     * @return self
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    /**
     * Set job priority
     * 
     * @param string $priority 'high', 'normal', or 'low'
     * @return self
     */
    public function priority(string $priority): self
    {
        if (!in_array($priority, ['high', 'normal', 'low'])) {
            throw new \InvalidArgumentException('Priority must be high, normal, or low');
        }
        $this->priority = $priority;
        return $this;
    }
    
    /**
     * Get job priority
     * 
     * @return string
     */
    public function getPriority(): string
    {
        return $this->priority;
    }
    
    /**
     * Add job dependency
     * 
     * @param string $jobId
     * @return self
     */
    public function dependsOn(string $jobId): self
    {
        $this->dependencies[] = $jobId;
        return $this;
    }
    
    /**
     * Chain another job to run after this one
     * 
     * @param Job $job
     * @param bool $onlyOnSuccess Run only if current job succeeds
     * @return self
     */
    public function chain(Job $job, bool $onlyOnSuccess = true): self
    {
        $this->chainedJobs[] = [
            'job' => $job,
            'onlyOnSuccess' => $onlyOnSuccess,
        ];
        return $this;
    }
    
    /**
     * Add metadata to the job
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function withMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * Add tags to the job
     * 
     * @param array $tags
     * @return self
     */
    public function withTags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }
    
    /**
     * Set success callback
     * 
     * @param callable $callback
     * @return self
     */
    public function onSuccess(callable $callback): self
    {
        $this->onSuccess = $callback;
        return $this;
    }
    
    /**
     * Set failure callback
     * 
     * @param callable $callback
     * @return self
     */
    public function onFailure(callable $callback): self
    {
        $this->onFailure = $callback;
        return $this;
    }
    
    /**
     * Set retry callback
     * 
     * @param callable $callback
     * @return self
     */
    public function onRetry(callable $callback): self
    {
        $this->onRetry = $callback;
        return $this;
    }
    
    /**
     * Run the job with retry support
     * 
     * @return bool
     */
    public function runWithRetry(): bool
    {
        $attempts = 0;
        $lastException = null;
        
        while ($attempts < $this->maxAttempts) {
            $attempts++;
            $this->retryCount = $attempts - 1;
            
            try {
                // Record execution start time
                $this->executionStartTime = microtime(true);
                
                // Run the job
                $result = $this->run();
                
                // Record execution time
                $this->executionTime = microtime(true) - $this->executionStartTime;
                
                if ($result && $this->isSuccessful()) {
                    // Call success callback
                    if ($this->onSuccess) {
                        call_user_func($this->onSuccess, $this);
                    }
                    
                    // Run chained jobs
                    $this->runChainedJobs(true);
                    
                    return true;
                }
                
                throw new RuntimeException('Job execution failed');
                
            } catch (Exception $e) {
                $lastException = $e;
                $this->output = $e->getMessage();
                $this->successful = false;
                
                if ($attempts < $this->maxAttempts) {
                    // Call retry callback
                    if ($this->onRetry) {
                        call_user_func($this->onRetry, $this, $attempts, $e);
                    }
                    
                    // Calculate delay before retry
                    $delay = $this->calculateRetryDelay($attempts);
                    if ($delay > 0) {
                        sleep($delay);
                    }
                } else {
                    // Final failure
                    if ($this->onFailure) {
                        call_user_func($this->onFailure, $this, $e);
                    }
                    
                    // Run chained jobs that should run on failure
                    $this->runChainedJobs(false);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Override parent run method to add timeout support
     * 
     * @return bool
     */
    public function run(): bool
    {
        // Check dependencies
        if (!$this->checkDependencies()) {
            $this->output = 'Dependencies not met';
            $this->successful = false;
            return false;
        }
        
        // Call parent run method
        $result = parent::run();
        
        // Apply timeout to process if applicable
        if ($this->process instanceof Process && $this->timeout > 0) {
            $this->process->setTimeout($this->timeout);
        }
        
        return $result;
    }
    
    /**
     * Get execution time in seconds
     * 
     * @return float
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }
    
    /**
     * Get job metadata
     * 
     * @param string|null $key
     * @return mixed
     */
    public function getMetadata(string $key = null)
    {
        if ($key === null) {
            return $this->metadata;
        }
        
        return $this->metadata[$key] ?? null;
    }
    
    /**
     * Get job tags
     * 
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }
    
    /**
     * Check if job has a specific tag
     * 
     * @param string $tag
     * @return bool
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }
    
    /**
     * Set queue ID
     * 
     * @param string $queueId
     * @return self
     */
    public function setQueueId(string $queueId): self
    {
        $this->queueId = $queueId;
        return $this;
    }
    
    /**
     * Get queue ID
     * 
     * @return string|null
     */
    public function getQueueId(): ?string
    {
        return $this->queueId;
    }
    
    /**
     * Get process (for background jobs)
     * 
     * @return Process|null
     */
    public function getProcess(): ?Process
    {
        return $this->process;
    }
    
    /**
     * Calculate retry delay based on strategy
     * 
     * @param int $attempt
     * @return int
     */
    protected function calculateRetryDelay(int $attempt): int
    {
        if ($this->retryStrategy === 'exponential') {
            return min($this->retryDelay * pow(2, $attempt - 1), 3600); // Max 1 hour
        }
        
        return $this->retryDelay;
    }
    
    /**
     * Check if dependencies are met
     * 
     * @return bool
     */
    protected function checkDependencies(): bool
    {
        if (empty($this->dependencies)) {
            return true;
        }
        
        // This would need to check against job history or status
        // For now, we'll assume dependencies are met
        // In a real implementation, this would check the ModernScheduler's job status
        return true;
    }
    
    /**
     * Run chained jobs
     * 
     * @param bool $success Whether the current job succeeded
     * @return void
     */
    protected function runChainedJobs(bool $success): void
    {
        foreach ($this->chainedJobs as $chainedJob) {
            $shouldRun = !$chainedJob['onlyOnSuccess'] || $success;
            
            if ($shouldRun) {
                $job = $chainedJob['job'];
                if ($job instanceof ModernJob) {
                    $job->runWithRetry();
                } else {
                    $job->run();
                }
            }
        }
    }
    
    /**
     * Convert job to array for serialization
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'command' => is_string($this->command) ? $this->command : 'Closure',
            'at' => $this->getAt(),
            'enabled' => $this->getEnabled(),
            'priority' => $this->priority,
            'max_attempts' => $this->maxAttempts,
            'retry_count' => $this->retryCount,
            'retry_delay' => $this->retryDelay,
            'retry_strategy' => $this->retryStrategy,
            'timeout' => $this->timeout,
            'dependencies' => $this->dependencies,
            'metadata' => $this->metadata,
            'tags' => $this->tags,
            'execution_time' => $this->executionTime,
            'successful' => $this->successful,
            'output' => $this->output,
        ];
    }
    
    /**
     * Create job from array
     * 
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $job = new self($data['command'] ?? '', [], $data['id'] ?? null);
        
        if (isset($data['at'])) {
            $job->at($data['at']);
        }
        
        if (isset($data['priority'])) {
            $job->priority($data['priority']);
        }
        
        if (isset($data['max_attempts'])) {
            $job->maxAttempts($data['max_attempts']);
        }
        
        if (isset($data['retry_delay']) && isset($data['retry_strategy'])) {
            $job->retryDelay($data['retry_delay'], $data['retry_strategy']);
        }
        
        if (isset($data['timeout'])) {
            $job->timeout($data['timeout']);
        }
        
        if (isset($data['dependencies'])) {
            foreach ($data['dependencies'] as $dep) {
                $job->dependsOn($dep);
            }
        }
        
        if (isset($data['metadata'])) {
            foreach ($data['metadata'] as $key => $value) {
                $job->withMetadata($key, $value);
            }
        }
        
        if (isset($data['tags'])) {
            $job->withTags($data['tags']);
        }
        
        return $job;
    }
}