<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use Cron\CronExpression;
use Grav\Common\Grav;
use Symfony\Component\Process\Process;

class Job
{
    use IntervalTrait;

    private $id;
    private $enabled = true;
    private $command;
    private $at;
    private $args = [];
    private $runInBackground = true;
    private $creationTime;
    private $executionTime;
    private $tempDir;
    private $lockFile;
    private $truthTest = true;
    private $output;
    private $returnCode = 0;
    private $outputTo = [];
    private $emailTo = [];
    private $emailConfig = [];
    private $before;
    private $after;
    private $whenOverlapping;
    private $outputMode;
    private $process;
    private $successful = false;
    private $backlink;

    /**
     * Create a new Job instance.
     *
     * @param  string|callable $command
     * @param  array $args
     * @param  string $id
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
        $this->creationTime = new \DateTime('now');
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
     * @return string
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
        if (\is_string($this->args)) {
            return $this->args;
        }

        return null;
    }

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
     * @param  \DateTime $date
     * @return bool
     */
    public function isDue(\DateTime $date = null)
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
     * @param string $link
     *
     * @return null|string
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
     * @param  string $tempDir The directory path for the lock files
     * @param  callable $whenOverlapping A callback to ignore job overlapping
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
            $this->whenOverlapping = function () {
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
            $args = \is_string($this->args) ? explode(' ', $this->args) : $this->args;
            $command = array_merge([$this->command], $args);
            $process = new Process($command);

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
        /** @var Process $process */
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
     */
    private function postRun()
    {
        if (count($this->outputTo) > 0) {
            foreach ($this->outputTo as $file) {
                $output_mode = $this->outputMode === 'append' ? FILE_APPEND | LOCK_EX : LOCK_EX;
                file_put_contents($file, $this->output, $output_mode);
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
            if ($content === null || !\is_string($content)) {
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
     * @throws \RuntimeException
     * @return string
     */
    private function exec()
    {
        $return_data = '';
        ob_start();
        try {
            $return_data = call_user_func_array($this->command, $this->args);
            $this->successful = true;
        } catch (\RuntimeException $e) {
            $this->successful = false;
        }
        $this->output = ob_get_clean() . (is_string($return_data) ? $return_data : '');

        $this->postRun();
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
            throw new \InvalidArgumentException('The email can be only string or array');
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
}

