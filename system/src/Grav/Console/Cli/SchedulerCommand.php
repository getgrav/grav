<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Cron\CronExpression;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Common\Scheduler\Scheduler;
use Grav\Console\ConsoleCommand;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

class SchedulerCommand extends ConsoleCommand
{
    protected function configure()
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
            ->setDescription('Run the Grav Scheduler.  Best when integrated with system cron')
            ->setHelp("Running without any options will force the Scheduler to run through it's jobs and process them");
    }

    protected function serve()
    {
//        error_reporting(1);
        $grav = Grav::instance();
        $grav->setup();

        $grav['uri']->init();
        $grav['config']->init();
        $grav['plugins']->init();
        $grav['themes']->init();
        $grav['backups']->init();

        // Initialize Plugins
        $grav->fireEvent('onPluginsInitialized');

        /** @var Scheduler $scheduler */
        $scheduler = $grav['scheduler'];
        $grav->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));

        $this->setHelp('foo');

        $io = new SymfonyStyle($this->input, $this->output);

        if ($this->input->getOption('jobs')) {
            // Show jobs list

            $jobs = $scheduler->getAllJobs();
            $job_states = $scheduler->getJobStates()->content();
            $rows = [];

            $table = new Table($this->output);
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
        } elseif ($this->input->getOption('details')) {
            $jobs = $scheduler->getAllJobs();
            $job_states = $scheduler->getJobStates()->content();

            $io->title('Job Details');

            $table = new Table($this->output);
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

        } elseif ($this->input->getOption('install')) {
            $io->title('Install Scheduler');

            if ($scheduler->isCrontabSetup()) {
                $io->success('All Ready! You have already set up Grav\'s Scheduler in your crontab');
            } else {
                $io->error('You still need to set up Grav\'s Scheduler in your crontab');
            }
            if (!Utils::isWindows()) {
                $io->note('To install, run the following command from your terminal:');
                $io->newLine();
                $io->text(trim($scheduler->getCronCommand()));
            } else {
                $io->note('To install, create a scheduled task in Windows.');
                $io->text('Learn more at https://learn.getgrav.org/advanced/scheduler');
            }
        } else {
            // Run scheduler
            $scheduler->run();

            if ($this->input->getOption('verbose')) {
                $io->title('Running Scheduled Jobs');
                $io->text($scheduler->getVerboseOutput());
            }
        }
    }
}
