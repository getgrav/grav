<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console;

use Grav\Common\Grav;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCommand extends Command
{
    use ConsoleTrait;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);
        $this->serve();
    }

    /**
     *
     */
    protected function serve()
    {

    }

    protected function displayGPMRelease()
    {
        $this->output->writeln('');
        $this->output->writeln('GPM Releases Configuration: <yellow>' . ucfirst(Grav::instance()['config']->get('system.gpm.releases')) . '</yellow>');
        $this->output->writeln('');
    }

}
