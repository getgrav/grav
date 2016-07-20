<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class NewProjectCommand extends ConsoleCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('new-project')
            ->setAliases(['newproject'])
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                'The destination directory of your new Grav project'
            )
            ->addOption(
                'symlink',
                's',
                InputOption::VALUE_NONE,
                'Symlink the required bits'
            )
            ->setDescription('Creates a new Grav project with all the dependencies installed')
            ->setHelp('The <info>new-project</info> command is a combination of the `setup` and `install` commands.\nCreates a new Grav instance and performs the installation of all the required dependencies.');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $sandboxCommand = $this->getApplication()->find('sandbox');
        $installCommand = $this->getApplication()->find('install');

        $sandboxArguments = new ArrayInput([
            'command'     => 'sandbox',
            'destination' => $this->input->getArgument('destination'),
            '-s'          => $this->input->getOption('symlink')
        ]);

        $installArguments = new ArrayInput([
            'command'     => 'install',
            'destination' => $this->input->getArgument('destination'),
            '-s'          => $this->input->getOption('symlink')
        ]);

        $sandboxCommand->run($sandboxArguments, $this->output);
        $installCommand->run($installArguments, $this->output);

    }
}
