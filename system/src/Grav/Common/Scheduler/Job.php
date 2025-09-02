<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use Closure;
use Cron\CronExpression;
use DateTime;
use Grav\Common\Grav;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use function call_user_func;
use function call_user_func_array;
use function count;
use function is_array;
use function is_callable;
use function is_string;

/**
 * Class Job
 * @package Grav\Common\Scheduler
 */
class Job
{
    use IntervalTrait;

    /** @var string */
    private $id;
    /** @var bool */
    private $enabled;
    /** @var callable|string */
    private $command;
    /** @var string */
    private $at;
    /** @var array */
    private $args = [];
    /** @var bool */
    private $runInBackground = true;
    /** @var DateTime */
    private $creationTime;
    /** @var CronExpression */
    private $executionTime;
    /** @var string */
    private $tempDir;
    /** @var string */
    private $lockFile;
    /** @var bool */
    private $truthTest = true;
    /** @var string */
    private $output;
    /** @var int */
    private $returnCode = 0;
    /** @var array */
    private $outputTo = [];
    /** @var array */
    private $emailTo = [];
    /** @var array */
    private $emailConfig = [];
    /** @var callable|null */
    private $before;
    /** @var callable|null */
    private $after;
    /** @var callable */
    private $whenOverlapping;
    /** @var string */
    private $outputMode;
    /** @var Process|null $process */
    private $process;
    /** @var bool */
    private $successful = false;
    /** @var string|null */
    private $backlink;
    
    // Modern Job features
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
    protected $executionDuration = 0;
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
     * Create a new Job instance.
     *
     * @param  string|callable $command
     * @param  array $args
     * @param  string|null $id
     */
    public function __construct($command, $args = [], $id = null)
    {
        if (is_string($id)) {
            $this->id = Grav::instance()['inflector']->hyphenize($id);
        } else {
            if (is_string($command)) {
                $this->id = md5($command);
            } else {
                /* @var object $command */
                $this->id = spl_object_hash($command);
            }
        }
        $this->creationTime = new DateTime('now');
        // initialize the directory path for lock files
        $this->tempDir = sys_get_temp_dir();
        $this->command = $command;
        $this->args = $args;
        // Set enabled state
        $status = Grav::instance()['config']->get('scheduler.status');
        $this->enabled = !(isset($status[$id]) && $status[$id] === 'disabled');
    }

    /**
     * Get the command
     *
     * @return Closure|string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Get the cron 'at' syntax for this job
     *
     * @return string
     */
    public function getAt()
    {
        return $this->at;
    }

    /**
     * Get the status of this job
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Get optional arguments
     *
     * @return string|null
     */
    public function getArguments()
    {
        if (is_string($this->args)) {
            return $this->args;
        }

        return null;
    }
    
    /**
     * Get raw arguments (array or string)
     *
     * @return array|string
     */
    public function getRawArguments()
    {
        return $this->args;
    }

    /**
     * @return CronExpression
     */
    public function getCronExpression()
    {
        return CronExpression::factory($this->at);
    }

    /**
     * Get the status of the last run for this job
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->successful;
    }

    /**
     * Get the Job id.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Check if the Job is due to run.
     * It accepts as input a DateTime used to check if
     * the job is due. Defaults to job creation time.
     * It also default the execution time if not previously defined.
     *
     * @param  DateTime|null $date
     * @return bool
     */
    public function isDue(DateTime $date = null)
    {
        // The execution time is being defaulted if not defined
        if (!$this->executionTime) {
            $this->at('* * * * *');
        }

        $date = $date ?? $this->creationTime;

        return $this->executionTime->isDue($date);
    }

    /**
     * Check if the Job is overlapping.
     *
     * @return bool
     */
    public function isOverlapping()
    {
        return $this->lockFile &&
            file_exists($this->lockFile) &&
            call_user_func($this->whenOverlapping, filemtime($this->lockFile)) === false;
    }

    /**
     * Force the Job to run in foreground.
     *
     * @return $this
     */
    public function inForeground()
    {
        $this->runInBackground = false;

        return $this;
    }

    /**
     * Sets/Gets an option backlink
     *
     * @param string|null $link
     * @return string|null
     */
    public function backlink($link = null)
    {
        if ($link) {
            $this->backlink = $link;
        }
        return $this->backlink;
    }


    /**
     * Check if the Job can run in background.
     *
     * @return bool
     */
    public function runInBackground()
    {
        return !(is_callable($this->command) || $this->runInBackground === false);
    }

    /**
     * This will prevent the Job from overlapping.
     * It prevents another instance of the same Job of
     * being executed if the previous is still running.
     * The job id is used as a filename for the lock file.
     *
     * @param  string|null $tempDir The directory path for the lock files
     * @param  callable|null $whenOverlapping A callback to ignore job overlapping
     * @return self
     */
    public function onlyOne($tempDir = null, callable $whenOverlapping = null)
    {
        if ($tempDir === null || !is_dir($tempDir)) {
            $tempDir = $this->tempDir;
        }
        $this->lockFile = implode('/', [
            trim($tempDir),
            trim($this->id) . '.lock',
        ]);
        if ($whenOverlapping) {
            $this->whenOverlapping = $whenOverlapping;
        } else {
            $this->whenOverlapping = static function () {
                return false;
            };
        }

        return $this;
    }

    /**
     * Configure the job.
     *
     * @param  array $config
     * @return self
     */
    public function configure(array $config = [])
    {
        // Check if config has defined a tempDir
        if (isset($config['tempDir']) && is_dir($config['tempDir'])) {
            $this->tempDir = $config['tempDir'];
        }

        return $this;
    }

    /**
     * Truth test to define if the job should run if due.
     *
     * @param  callable $fn
     * @return self
     */
    public function when(callable $fn)
    {
        $this->truthTest = $fn();

        return $this;
    }

    /**
     * Run the job.
     *
     * @return bool
     */
    public function run()
    {
        // Check dependencies (modern feature)
        if (!$this->checkDependencies()) {
            $this->output = 'Dependencies not met';
            $this->successful = false;
            return false;
        }
        
        // If the truthTest failed, don't run
        if ($this->truthTest !== true) {
            return false;
        }

        // If overlapping, don't run
        if ($this->isOverlapping()) {
            return false;
        }

        // Write lock file if necessary
        $this->createLockFile();

        // Call before if required
        if (is_callable($this->before)) {
            call_user_func($this->before);
        }

        // If command is callable...
        if (is_callable($this->command)) {
            $this->output = $this->exec();
        } else {
            $args = is_string($this->args) ? explode(' ', $this->args) : $this->args;
            $command = array_merge([$this->command], $args);
            $process = new Process($command);
            
            // Apply timeout if set (modern feature)
            if ($this->timeout > 0) {
                $process->setTimeout($this->timeout);
            }

            $this->process = $process;

            if ($this->runInBackground()) {
                $process->start();
            } else {
                $process->run();
                $this->finalize();
            }
        }

        return true;
    }

    /**
     * Finish up processing the job
     *
     * @return void
     */
    public function finalize()
    {
        $process = $this->process;

        if ($process) {
            $process->wait();

            if ($process->isSuccessful()) {
                $this->successful = true;
                $this->output =  $process->getOutput();
            } else {
                $this->successful = false;
                $this->output =  $process->getErrorOutput();
            }

            $this->postRun();

            unset($this->process);
        }
    }

    /**
     * Things to run after job has run
     *
     * @return void
     */
    private function postRun()
    {
        if (count($this->outputTo) > 0) {
            foreach ($this->outputTo as $file) {
                $output_mode = $this->outputMode === 'append' ? FILE_APPEND | LOCK_EX : LOCK_EX;
                $timestamp = (new DateTime('now'))->format('c');
                $output = $timestamp . "\n" . str_pad('', strlen($timestamp), '>') . "\n" . $this->output;
                file_put_contents($file, $output, $output_mode);
            }
        }

        // Send output to email
        $this->emailOutput();

        // Call any callback defined
        if (is_callable($this->after)) {
            call_user_func($this->after, $this->output, $this->returnCode);
        }

        $this->removeLockFile();
    }

    /**
     * Create the job lock file.
     *
     * @param  mixed $content
     * @return void
     */
    private function createLockFile($content = null)
    {
        if ($this->lockFile) {
            if ($content === null || !is_string($content)) {
                $content = $this->getId();
            }
            file_put_contents($this->lockFile, $content);
        }
    }

    /**
     * Remove the job lock file.
     *
     * @return void
     */
    private function removeLockFile()
    {
        if ($this->lockFile && file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * Execute a callable job.
     *
     * @return string
     * @throws RuntimeException
     */
    private function exec()
    {
        $return_data = '';
        ob_start();
        try {
            $return_data = call_user_func_array($this->command, $this->args);
            $this->successful = true;
        } catch (RuntimeException $e) {
            $return_data = $e->getMessage();
            $this->successful = false;
        }
        $this->output = ob_get_clean() . (is_string($return_data) ? $return_data : '');

        $this->postRun();

        return $this->output;
    }

    /**
     * Set the file/s where to write the output of the job.
     *
     * @param  string|array $filename
     * @param  bool $append
     * @return self
     */
    public function output($filename, $append = false)
    {
        $this->outputTo = is_array($filename) ? $filename : [$filename];
        $this->outputMode = $append === false ? 'overwrite' : 'append';

        return $this;
    }

    /**
     * Get the job output.
     *
     * @return mixed
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set the emails where the output should be sent to.
     * The Job should be set to write output to a file
     * for this to work.
     *
     * @param  string|array $email
     * @return self
     */
    public function email($email)
    {
        if (!is_string($email) && !is_array($email)) {
            throw new InvalidArgumentException('The email can be only string or array');
        }

        $this->emailTo = is_array($email) ? $email : [$email];
        // Force the job to run in foreground
        $this->inForeground();

        return $this;
    }

    /**
     * Email the output of the job, if any.
     *
     * @return bool
     */
    private function emailOutput()
    {
        if (!count($this->outputTo) || !count($this->emailTo)) {
            return false;
        }

        if (is_callable('Grav\Plugin\Email\Utils::sendEmail')) {
            $subject ='Grav Scheduled Job [' . $this->getId() . ']';
            $content = "<h1>Output from Job ID: {$this->getId()}</h1>\n<h4>Command: {$this->getCommand()}</h4><br /><pre style=\"font-size: 12px; font-family: Monaco, Consolas, monospace\">\n".$this->getOutput()."\n</pre>";
            $to = $this->emailTo;

            \Grav\Plugin\Email\Utils::sendEmail($subject, $content, $to);
        }

        return true;
    }

    /**
     * Set function to be called before job execution
     * Job object is injected as a parameter to callable function.
     *
     * @param callable $fn
     * @return self
     */
    public function before(callable $fn)
    {
        $this->before = $fn;

        return $this;
    }

    /**
     * Set a function to be called after job execution.
     * By default this will force the job to run in foreground
     * because the output is injected as a parameter of this
     * function, but it could be avoided by passing true as a
     * second parameter. The job will run in background if it
     * meets all the other criteria.
     *
     * @param  callable $fn
     * @param  bool $runInBackground
     * @return self
     */
    public function then(callable $fn, $runInBackground = false)
    {
        $this->after = $fn;
        // Force the job to run in foreground
        if ($runInBackground === false) {
            $this->inForeground();
        }

        return $this;
    }
    
    // Modern Job Methods
    
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
            throw new InvalidArgumentException('Priority must be high, normal, or low');
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
                $this->executionDuration = microtime(true) - $this->executionStartTime;
                
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
                
            } catch (\Exception $e) {
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
     * Get execution time in seconds
     * 
     * @return float
     */
    public function getExecutionTime(): float
    {
        return $this->executionDuration;
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
        // In a real implementation, this would check the Scheduler's job status
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
                if (method_exists($job, 'runWithRetry')) {
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
            'execution_time' => $this->executionDuration,
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
