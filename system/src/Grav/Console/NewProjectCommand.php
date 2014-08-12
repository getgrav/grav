<?php
namespace Grav\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Symfony\Component\Yaml\Yaml;

class NewProjectCommand extends Command {

    protected function configure() {
        $this
        ->setName("new-project")
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
        ->setDescription("Creates a new Grav project with all the dependencies included")
        ->setHelp('The <info>new</info> command provides clone and symlink installation chores');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $setupCommand   = $this->getApplication()->find('setup');
        $installCommand = $this->getApplication()->find('install');

        $setupArguments = new ArrayInput(array(
                                            'command'     => 'setup',
                                            'destination' => $input->getArgument('destination'),
                                            '-s'          => $input->getOption('symlink')
                                            ));

        $installArguments = new ArrayInput(array(
                                                'command'     => 'install',
                                                'destination' => $input->getArgument('destination'),
                                                '-s'          => $input->getOption('symlink')
                                                ));

        $setupCommand->run($setupArguments, $output);
        $installCommand->run($installArguments, $output);

    }
}
