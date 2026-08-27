<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Cron\CronExpression;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Common\Scheduler\Scheduler;
use Grav\Console\GravCommand;
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
            ->addOption(
                'catch-up',
                'c',
                InputOption::VALUE_NONE,
                'Also run jobs that missed the last slot their schedule gave them'
            )
            ->setDescription('Run the Grav Scheduler.  Best when integrated with system cron')
            ->setHelp("Running without any options will process the Scheduler jobs based on their cron schedule. Use --catch-up to also pick up jobs that missed their last slot, which is what you want on a site with no cron entry. Use --force to run every enabled job immediately.");
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
        // The scheduler fires onSchedulerInitialized itself, once. Firing it here as well
        // would register every system and plugin job a second time.
        $scheduler->initializeJobs();

        $input = $this->getInput();
        $io = $this->getIO();
        $error = 0;

        $run = $input->getOption('run');
        $showDetails = $input->getOption('details');
        $showJobs = $input->getOption('jobs');
        $forceRun = $input->getOption('force');
        $catchUp = $input->getOption('catch-up');

        // Handle running jobs first if -r flag is present
        if ($run !== false) {
            if ($run === null || $run === '') {
                // Run all jobs when -r is provided without a specific job ID. Routed through the
                // scheduler rather than run job-by-job here so the run is recorded in status.yaml
                // like any other, which is what --catch-up and the admin's Run button read.
                $io->title('Force Run All Jobs');

                $scheduler->setRunTrigger('manual')->run(null, false, Scheduler::RUN_ALL);

                $hasOutput = false;

                foreach ($scheduler->getJobsRun() as $job) {
                    $io->section('Running: ' . $job->getId());

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

                if (!$hasOutput) {
                    $io->note('All enabled jobs completed');
                }
            } else {
                // Run specific job
                $io->title('Force Run Job: ' . $run);

                $job = $scheduler->setRunTrigger('manual')->runJob($run);

                if ($job) {
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
                // Jobs registered by plugins are closures, which have no string form.
                $command = $job->getCommand();
                $command = is_string($command) ? $command : '(closure)';
                $row = [
                    $job->getId(),
                    "<white>{$command}</white>",
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
                // A job that has never run has no state at all.
                $job_state = $job_states[$job->getId()] ?? [];
                $error = isset($job_state['error']) ? trim((string) $job_state['error']) : false;

                /** @var CronExpression|null $expression */
                $expression = $job->getCronExpression();

                $row = [];
                $row[] = $job->getId();
                if (!is_null($job_state['last-run'] ?? null)) {
                    $row[] = '<yellow>' . date('Y-m-d H:i', $job_state['last-run']) . '</yellow>';
                } else {
                    $row[] = '<yellow>Never</yellow>';
                }

                if ($expression) {
                    $next_run = $expression->getNextRunDate();
                    $row[] = '<yellow>' . $next_run->format('Y-m-d H:i') . '</yellow>';
                } else {
                    $row[] = '<error>Invalid cron</error>';
                }

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

                // From the CLI the environment is 'cli', so the line above only carries --env when
                // this command was itself given one. Point at the overrides that exist so nobody
                // installs a cron that silently skips them (#4248).
                if (null === $scheduler->getOverrideEnvironment()) {
                    $environments = $this->findEnvironmentOverrides();
                    if ($environments) {
                        $io->newLine();
                        $io->note(sprintf(
                            'This site has environment overrides in user/env/ (%s). Settings and custom jobs defined only there are NOT loaded by the line above. Re-run with --env=<name>, for example "bin/grav scheduler --env=%s --install", to get a cron line that loads them, or export GRAV_ENVIRONMENT=<name> in the crontab.',
                            implode(', ', $environments),
                            $environments[0]
                        ));
                    }
                }
            } else {
                $io->note("To $verb, create a scheduled task in Windows.");
                $io->text('Learn more at https://learn.getgrav.org/advanced/scheduler');
            }
        } elseif (!$showJobs && !$showDetails && $run === false) {
            // Run scheduler only if no other options were provided
            $mode = $catchUp ? Scheduler::RUN_OVERDUE : Scheduler::RUN_DUE;
            $scheduler->run(null, $forceRun, $mode);

            if ($input->getOption('verbose')) {
                $io->title('Running Scheduled Jobs');
                $io->text($scheduler->getVerboseOutput());
            }
        }

        return $error;
    }

    /**
     * Names of the environments that have their own config folder (user/env/<name>/config).
     *
     * @return string[]
     */
    private function findEnvironmentOverrides(): array
    {
        $root = Grav::instance()['locator']->findResource('user://env', true);
        if (!$root || !is_dir($root)) {
            return [];
        }

        $names = [];
        foreach (glob($root . '/*/config', GLOB_ONLYDIR) ?: [] as $dir) {
            $names[] = basename(dirname($dir));
        }
        sort($names);

        return $names;
    }
}
