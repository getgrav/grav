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

class SchedulerCommand extends ConsoleCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('scheduler')
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
        $grav->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));

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
