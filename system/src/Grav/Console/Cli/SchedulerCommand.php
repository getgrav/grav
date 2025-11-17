<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Cron\CronExpression;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Common\Scheduler\Scheduler;
use Grav\Console\GravCommand;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use function is_null;

/**
 * Class SchedulerCommand
 * @package Grav\Console\Cli
 */
class SchedulerCommand extends GravCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('scheduler')
            ->addOption(
                'install',
                'i',
                InputOption::VALUE_NONE,
                'Show Install Command'
            )
            ->addOption(
                'jobs',
                'j',
                InputOption::VALUE_NONE,
                'Show Jobs Summary'
            )
            ->addOption(
                'details',
                'd',
                InputOption::VALUE_NONE,
                'Show Job Details'
            )
            ->addOption(
                'run',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Force run all jobs or a specific job if you specify a specific Job ID',
                false
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force all due jobs to run regardless of their schedule'
            )
            ->setDescription('Run the Grav Scheduler.  Best when integrated with system cron')
            ->setHelp("Running without any options will process the Scheduler jobs based on their cron schedule. Use --force to run all jobs immediately.");
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $this->initializePlugins();

        $grav = Grav::instance();
        $grav['backups']->init();
        $this->initializePages();
        $this->initializeThemes();

        /** @var Scheduler $scheduler */
        $scheduler = $grav['scheduler'];
        $grav->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));

        $input = $this->getInput();
        $io = $this->getIO();
        $error = 0;

        $run = $input->getOption('run');
        $showDetails = $input->getOption('details');
        $showJobs = $input->getOption('jobs');
        $forceRun = $input->getOption('force');

        // Handle running jobs first if -r flag is present
        if ($run !== false) {
            if ($run === null || $run === '') {
                // Run all jobs when -r is provided without a specific job ID
                $io->title('Force Run All Jobs');
                
                $jobs = $scheduler->getAllJobs();
                $hasOutput = false;
                
                foreach ($jobs as $job) {
                    if ($job->getEnabled()) {
                        $io->section('Running: ' . $job->getId());
                        $job->inForeground()->run();
                        
                        if ($job->isSuccessful()) {
                            $io->success('Job ' . $job->getId() . ' ran successfully');
                        } else {
                            $error = 1;
                            $io->error('Job ' . $job->getId() . ' failed to run');
                        }
                        
                        $output = $job->getOutput();
                        if ($output) {
                            $io->write($output);
                            $hasOutput = true;
                        }
                    }
                }
                
                if (!$hasOutput) {
                    $io->note('All enabled jobs completed');
                }
            } else {
                // Run specific job
                $io->title('Force Run Job: ' . $run);

                $job = $scheduler->getJob($run);

                if ($job) {
                    $job->inForeground()->run();

                    if ($job->isSuccessful()) {
                        $io->success('Job ran successfully...');
                    } else {
                        $error = 1;
                        $io->error('Job failed to run successfully...');
                    }

                    $output = $job->getOutput();

                    if ($output) {
                        $io->write($output);
                    }
                } else {
                    $error = 1;
                    $io->error('Could not find a job with id: ' . $run);
                }
            }

            // Add separator if we're going to show details after
            if ($showDetails) {
                $io->newLine();
            }
        }

        if ($showJobs) {
            // Show jobs list

            $jobs = $scheduler->getAllJobs();
            $job_states = (array)$scheduler->getJobStates()->content();
            $rows = [];

            $table = new Table($io);
            $table->setStyle('box');
            $headers = ['Job ID', 'Command', 'Run At', 'Status', 'Last Run', 'State'];

            $io->title('Scheduler Jobs Listing');

            foreach ($jobs as $job) {
                $job_status = ucfirst($job_states[$job->getId()]['state'] ?? 'ready');
                $last_run = $job_states[$job->getId()]['last-run'] ?? 0;
                $status = $job_status === 'Failure' ? "<red>{$job_status}</red>" : "<green>{$job_status}</green>";
                $state = $job->getEnabled() ? '<cyan>Enabled</cyan>' : '<red>Disabled</red>';
                $row = [
                    $job->getId(),
                    "<white>{$job->getCommand()}</white>",
                    "<magenta>{$job->getAt()}</magenta>",
                    $status,
                    '<yellow>' . ($last_run === 0 ? 'Never' : date('Y-m-d H:i', $last_run)) . '</yellow>',
                    $state,

                ];
                $rows[] = $row;
            }

            if (!empty($rows)) {
                $table->setHeaders($headers);
                $table->setRows($rows);
                $table->render();
            } else {
                $io->text('no jobs found...');
            }

            $io->newLine();
            $io->note('For error details run "bin/grav scheduler -d"');
            $io->newLine();
        }
        
        if ($showDetails) {
            $jobs = $scheduler->getAllJobs();
            $job_states = (array)$scheduler->getJobStates()->content();

            $io->title('Job Details');

            $table = new Table($io);
            $table->setStyle('box');
            $table->setHeaders(['Job ID', 'Last Run', 'Next Run', 'Errors']);
            $rows = [];

            foreach ($jobs as $job) {
                $job_state = $job_states[$job->getId()];
                $error = isset($job_state['error']) ? trim($job_state['error']) : false;

                /** @var CronExpression $expression */
                $expression = $job->getCronExpression();
                $next_run = $expression->getNextRunDate();

                $row = [];
                $row[] = $job->getId();
                if (!is_null($job_state['last-run'])) {
                    $row[] = '<yellow>' . date('Y-m-d H:i', $job_state['last-run']) . '</yellow>';
                } else {
                    $row[] = '<yellow>Never</yellow>';
                }
                $row[] = '<yellow>' . $next_run->format('Y-m-d H:i') . '</yellow>';

                if ($error) {
                    $row[] = "<error>{$error}</error>";
                } else {
                    $row[] = '<green>None</green>';
                }
                $rows[] = $row;
            }

            $table->setRows($rows);
            $table->render();
        }
        
        if ($input->getOption('install')) {
            $io->title('Install Scheduler');

            $verb = 'install';

            if ($scheduler->isCrontabSetup()) {
                $io->success('All Ready! You have already set up Grav\'s Scheduler in your crontab. You can validate this by running "crontab -l" to list your current crontab entries.');
                $verb = 'reinstall';
            } else {
                $user = $scheduler->whoami();
                $error = 1;
                $io->error('Can\'t find a crontab for ' . $user . '. You need to set up Grav\'s Scheduler in your crontab');
            }
            if (!Utils::isWindows()) {
                $io->note("To $verb, run the following command from your terminal:");
                $io->newLine();
                $io->text(trim($scheduler->getCronCommand()));
            } else {
                $io->note("To $verb, create a scheduled task in Windows.");
                $io->text('Learn more at https://learn.getgrav.org/advanced/scheduler');
            }
        } elseif (!$showJobs && !$showDetails && $run === false) {
            // Run scheduler only if no other options were provided
            $scheduler->run(null, $forceRun);

            if ($input->getOption('verbose')) {
                $io->title('Running Scheduled Jobs');
                $io->text($scheduler->getVerboseOutput());
            }
        }

        return $error;
    }
}
