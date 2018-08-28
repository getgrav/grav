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
        $grav->fireEvent('onSchedulerInitialized');

        $scheduler->run();

        if ($this->input->getOption('details')) {
            $output = $scheduler->getVerboseOutput();
            $this->output->writeln('<green>'.$output.'</green>');
        }
    }
}
