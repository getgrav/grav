<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Grav;
use Grav\Common\Scheduler\Scheduler;
use Grav\Console\ConsoleCommand;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\Console\Input\InputOption;
use League\CLImate\CLImate;

class SchedulerCommand extends ConsoleCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('scheduler')
            ->addOption(
                'jobs',
                'j',
                InputOption::VALUE_NONE,
                'Show Jobs Summary'
            )
            ->addOption(
                'errors',
                'e',
                InputOption::VALUE_NONE,
                'Show Errors'
            )
            ->setDescription('Run the Grav Scheduler.  Best when integrated with system cron')
            ->setHelp("Running without any options will force the Scheduler to run through it's jobs and process them");
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
//        error_reporting(1);
        $grav = Grav::instance();

        $grav['uri']->init();
        $grav['config']->init();
        $grav['streams'];
        $grav['plugins']->init();
        $grav['themes']->init();

        // Initialize Plugins
        $grav->fireEvent('onPluginsInitialized');

        /** @var Scheduler $scheduler */
        $scheduler = $grav['scheduler'];
        $grav->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));

        if ($this->input->getOption('jobs')) {
            // Show jobs list
            $climate = new CLImate;
            $climate->extend('Grav\Console\TerminalObjects\Table');;

            $jobs = $scheduler->getAllJobs();
            $table = [];

            $this->output->writeln('');
            $this->output->writeln('<magenta>Scheduler Jobs Listing</magenta>');
            $this->output->writeln('');

            $job_states = $scheduler->getJobStates()->content();

            foreach ($jobs as $job) {
                $job_status = ucfirst($job_states[$job->getId()]['state'] ?? 'ready');
                $last_run = $job_states[$job->getId()]['last-run'] ?? 0;
                $status = $job_status === 'Failure' ? "<red>{$job_status}</red>" : "<green>{$job_status}</green>";
                $state = $job->getEnabled() ? "<blue>Enabled</blue>" : "Disabled";
                $row = [
                    'ID' => $job->getId(),
                    'Command' => "<white><bold>{$job->getCommand()}</bold></white>",
                    'Run At' => "<magenta>{$job->getAt()}</magenta>",
                    'Status' => "<bold>{$status}</bold>",
                    "Last Run" => "<yellow>" . ($last_run === 0 ? 'Never' : date('Y-m-d H:i', $last_run)) . "</yellow>",
                    'State' => $state,

                ];
                $table[] = $row;
            }

            $climate->table($table);

            $this->output->writeln('');
            $this->output->writeln('<yellow>NOTE: For error details run "bin/grav scheduler -e"</yellow>');
            $this->output->writeln('');
        } elseif ($this->input->getOption('errors')) {
            $this->output->writeln('');
            $this->output->writeln('<magenta>Job Errors</magenta>');
            $this->output->writeln('');

            $jobs = $scheduler->getAllJobs();
            $job_states = $scheduler->getJobStates()->content();

            foreach ($jobs as $job) {
                $job_state = $job_states[$job->getId()];
                if (isset($job_state['error'])) {
                    $this->output->writeln("Job ID:    {$job->getId()}");
                    $this->output->writeln("Command:   <white>{$job->getCommand()}</white>");
                    $this->output->writeln("Last Run:  <yellow>" . date('Y-m-d H:i', $job_state['last-run']) . "</yellow>");
                    $this->output->writeln("Error Msg: <red>{$job_state['error']}</red>");
                    $this->output->writeln('');
                }
            }

        } else {
            // Run scheduler
            $scheduler->run();

            if ($this->input->getOption('verbose')) {
                $this->output->writeln('');
                $this->output->writeln('<magenta>Running Scheduled Jobs</magenta>');
                $this->output->writeln('');
                $output = $scheduler->getVerboseOutput();
                $this->output->writeln($output);
            }
        }
    }
}
