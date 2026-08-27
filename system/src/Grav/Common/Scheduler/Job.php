<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use Closure;
use Cron\CronExpression;
use DateTime;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Symfony\Component\Process\PhpExecutableFinder;
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
    public function __construct($command, private $args = [], $id = null)
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
        $this->tempDir = static::getDefaultTempDir();
        $this->command = $command;
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
     * Set the enabled state of this job
     *
     * Used to seed a default state at registration time (e.g. from a profile
     * flag). An explicit entry in the scheduler `status` config still takes
     * precedence, as that is applied in the constructor.
     *
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool) $enabled;

        return $this;
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
     * @return CronExpression|null
     */
    public function getCronExpression()
    {
        try {
            // A job registered without a schedule runs every minute, which is what isDue()
            // has always assumed. Passing the null straight through was a type error.
            return CronExpression::factory($this->at ?: '* * * * *');
        } catch (\InvalidArgumentException $e) {
            // Invalid cron expression - return null to prevent DoS
            return null;
        }
    }

    /**
     * Validate a cron expression
     *
     * @param string $expression
     * @return bool
     */
    public static function isValidCronExpression(string $expression): bool
    {
        try {
            CronExpression::factory($expression);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
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
    public function isDue(?DateTime $date = null)
    {
        // The expression is parsed lazily on first use (see IntervalTrait::at()).
        // As before, a missing or invalid expression defaults to every minute.
        if (!$this->executionTime) {
            try {
                $this->executionTime = CronExpression::factory($this->at ?: '* * * * *');
            } catch (\InvalidArgumentException $e) {
                $this->executionTime = CronExpression::factory('* * * * *');
            }
        }

        $date ??= $this->creationTime;

        return $this->executionTime->isDue($date);
    }

    /**
     * The command as an argv array, with the PHP binary in front when it is one of Grav's own
     * CLI scripts.
     *
     * Those scripts start with a `#!/usr/bin/env php` line. That works from a shell, and fails
     * from the web server, where php is usually not on PATH -- so a job that ran perfectly from
     * cron reported "env: php: No such file or directory" the moment it was run from the admin.
     *
     * @return array
     */
    private function resolveCommand(): array
    {
        $command = (string) $this->command;

        // Process treats a single string as the name of the executable, so a job registered as a
        // whole command line -- 'bin/plugin seo-magic queue' -- sent it looking for a file whose
        // name contained spaces. That is an easy enough mistake to make that it is worth handling
        // here, but only when nothing on disk actually goes by the whole string.
        $argv = is_file($this->absolutePath($command)) ? [$command] : $this->splitCommandLine($command);

        $script = $this->absolutePath($argv[0]);

        // Anything that is not a PHP script shipped inside this install runs exactly as given.
        if (!is_file($script) || !$this->hasPhpShebang($script)) {
            return $argv;
        }

        $php = (new PhpExecutableFinder())->find();
        if (!$php) {
            return $argv;
        }

        $argv[0] = $script;
        array_unshift($argv, $php);

        return $argv;
    }

    /**
     * Resolve a command against the Grav install, so a job can name `bin/grav` the way the
     * documentation does.
     *
     * @param string $path
     * @return string
     */
    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return rtrim(GRAV_ROOT, '/') . '/' . $path;
    }

    /**
     * Split a command line into its arguments, keeping quoted sections whole.
     *
     * @param string $command
     * @return array
     */
    private function splitCommandLine(string $command): array
    {
        if (!preg_match_all('/"([^"]*)"|\'([^\']*)\'|(\S+)/', $command, $matches, PREG_SET_ORDER)) {
            return [$command];
        }

        $argv = [];
        foreach ($matches as $token) {
            $argv[] = $token[3] ?? $token[2] ?? $token[1];
        }

        return $argv ?: [$command];
    }

    /**
     * Whether a file starts with a shebang naming php.
     *
     * @param string $file
     * @return bool
     */
    private function hasPhpShebang(string $file): bool
    {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return false;
        }

        $line = (string) fgets($handle, 128);
        fclose($handle);

        return str_starts_with($line, '#!') && str_contains($line, 'php');
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
    public function onlyOne($tempDir = null, ?callable $whenOverlapping = null)
    {
        if ($tempDir === null || !is_dir($tempDir)) {
            $tempDir = $this->tempDir;
        }
        if (!is_dir($tempDir)) {
            Folder::create($tempDir);
        }
        $this->lockFile = implode('/', [
            trim($tempDir),
            trim($this->id) . '.lock',
        ]);
        if ($whenOverlapping) {
            $this->whenOverlapping = $whenOverlapping;
        } else {
            $this->whenOverlapping = static fn() => false;
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

        // Write lock file if necessary. Refuse to run rather than run unprotected
        // when the lock cannot be taken.
        if (!$this->createLockFile()) {
            $this->output = 'Unable to create lock file';
            $this->successful = false;

            return false;
        }

        // Call before if required
        if (is_callable($this->before)) {
            call_user_func($this->before);
        }

        // If command is callable...
        if (is_callable($this->command)) {
            $this->output = $this->exec();
        } else {
            $args = is_string($this->args) ? explode(' ', $this->args) : $this->args;
            $command = array_merge($this->resolveCommand(), $args);

            // Command jobs need proc_open. Rather than letting Symfony's Process throw from
            // its constructor -- which took down the whole scheduler run, and the admin
            // Scheduler page with it, on hosts that disable it -- record this job as failed
            // with an explanation and let the remaining jobs carry on.
            if (!Scheduler::isProcessAvailable()) {
                $this->output = 'Cannot run command jobs: this PHP installation has proc_open disabled.';
                $this->successful = false;
                $this->removeLockFile();

                return false;
            }

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
        // Everything from here on is bookkeeping around a job that has already run. None of it
        // is allowed to throw: an unwritable output file, a mistyped notification address or a
        // careless after() callback used to take down the entire scheduler run with it, losing
        // the recorded state of every job that had already succeeded.
        if (count($this->outputTo) > 0) {
            foreach ($this->outputTo as $file) {
                try {
                    $output_mode = $this->outputMode === 'append' ? FILE_APPEND | LOCK_EX : LOCK_EX;
                    $timestamp = (new DateTime('now'))->format('c');
                    $output = $timestamp . "\n" . str_pad('', strlen($timestamp), '>') . "\n" . $this->output;
                    file_put_contents($file, $output, $output_mode);
                } catch (Throwable $e) {
                    $this->logPostRunFailure('write output to ' . $file, $e);
                }
            }
        }

        // Send output to email
        try {
            $this->emailOutput();
        } catch (Throwable $e) {
            $this->logPostRunFailure('email the output', $e);
        }

        // Call any callback defined
        if (is_callable($this->after)) {
            try {
                call_user_func($this->after, $this->output, $this->returnCode);
            } catch (Throwable $e) {
                $this->logPostRunFailure('run the after() callback', $e);
            }
        }

        $this->removeLockFile();
    }

    /**
     * Record a post-run step that failed, without letting it stop the run.
     *
     * @param string $what
     * @param Throwable $e
     * @return void
     */
    private function logPostRunFailure(string $what, Throwable $e): void
    {
        try {
            Grav::instance()['log']->warning(sprintf(
                'Scheduler job "%s" ran, but failed to %s: %s',
                $this->getId(),
                $what,
                $e->getMessage()
            ));
        } catch (Throwable $ignored) {
            // Logging is the last thing that should be able to break a run.
        }
    }

    /**
     * Resolve the default directory used for job lock files.
     *
     * Locks live inside the Grav install (`tmp://scheduler`) rather than in the
     * system temp directory. On a shared host the system temp directory is
     * world-writable, so any other local account could pre-place a symlink at the
     * predictable `<tempDir>/<job-id>.lock` path and redirect the lock write to a
     * file of its choosing. (GHSA-q8w8-6cq5-j4h2)
     *
     * @return string
     */
    private static function getDefaultTempDir(): string
    {
        $locator = Grav::instance()['locator'] ?? null;
        if ($locator) {
            $path = $locator->findResource('tmp://scheduler', true, true);
            if ($path) {
                return $path;
            }
        }

        return sys_get_temp_dir();
    }

    /**
     * Create the job lock file.
     *
     * @return bool True if the lock was taken, or if no lock is configured.
     */
    private function createLockFile(mixed $content = null)
    {
        if (!$this->lockFile) {
            return true;
        }

        if ($content === null || !is_string($content)) {
            $content = $this->getId();
        }

        // Never write through a symlink: a link pre-placed at the lock path would
        // send the write to whatever it points at. Note that fopen() with 'x' is
        // not a portable substitute here, because on Darwin O_CREAT|O_EXCL against
        // a dangling symlink still creates the target.
        if (is_link($this->lockFile)) {
            return false;
        }

        return file_put_contents($this->lockFile, $content) !== false;
    }

    /**
     * Remove the job lock file.
     *
     * @return void
     */
    private function removeLockFile()
    {
        // is_link() also matches a dangling symlink, which file_exists() reports as
        // absent. Without it a stale link would sit there and permanently convince
        // isOverlapping() that the job is already running.
        if ($this->lockFile && (is_link($this->lockFile) || file_exists($this->lockFile))) {
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
            $command = $this->getCommand();
            $command = is_string($command) ? $command : 'Closure';
            $subject ='Grav Scheduled Job [' . $this->getId() . ']';
            $content = "<h1>Output from Job ID: {$this->getId()}</h1>\n<h4>Command: {$command}</h4><br /><pre style=\"font-size: 12px; font-family: Monaco, Consolas, monospace\">\n".$this->getOutput()."\n</pre>";
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
     * @return self
     */
    public function withMetadata(string $key, mixed $value): self
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
    public function getMetadata(?string $key = null)
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
            return min($this->retryDelay * 2 ** ($attempt - 1), 3600); // Max 1 hour
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
