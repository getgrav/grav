<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Scheduler\Scheduler;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;

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
                'details',
                'd',
                InputOption::VALUE_NONE,
                'Verbose output from command'
            )
            ->setDescription('Run the Grav Scheduler.  Best when integrated with system cron')
            ->setHelp("flush this out...");
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $scheduler = new Scheduler();

        $scheduler->loadSavedJobs();

        $scheduler->run();
        if ($this->input->getOption('details')) {
            $this->output->writeln('<green>Run</green>');
        }
    }
}
