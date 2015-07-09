<?php
namespace Grav\Console\Cli;

use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NewProjectCommand
 * @package Grav\Console\Cli
 */
class NewProjectCommand extends Command
{
    use ConsoleTrait;

    /**
     *
     */
    protected function configure()
    {
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
            ->setDescription("Creates a new Grav project with all the dependencies installed")
            ->setHelp("The <info>new-project</info> command is a combination of the `setup` and `install` commands.\nCreates a new Grav instance and performs the installation of all the required dependencies.");
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);

        $sandboxCommand = $this->getApplication()->find('sandbox');
        $installCommand = $this->getApplication()->find('install');

        $sandboxArguments = new ArrayInput(array(
            'command'     => 'sandbox',
            'destination' => $input->getArgument('destination'),
            '-s'          => $input->getOption('symlink')
        ));

        $installArguments = new ArrayInput(array(
            'command'     => 'install',
            'destination' => $input->getArgument('destination'),
            '-s'          => $input->getOption('symlink')
        ));

        $sandboxCommand->run($sandboxArguments, $output);
        $installCommand->run($installArguments, $output);

    }
}
