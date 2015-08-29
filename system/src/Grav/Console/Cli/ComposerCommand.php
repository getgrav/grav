<?php
namespace Grav\Console\Cli;

use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ComposerCommand
 * @package Grav\Console\Cli
 */
class ComposerCommand extends Command
{
    use ConsoleTrait;

    /**
     * @var
     */
    protected $config;
    /**
     * @var
     */
    protected $local_config;
    /**
     * @var
     */
    protected $destination;
    /**
     * @var
     */
    protected $user_path;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("composer")
            ->addOption(
                'install',
                'i',
                InputOption::VALUE_NONE,
                'install the dependencies'
            )
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                'update the dependencies'
            )
            ->setDescription("Updates the composer vendor dependencies needed by Grav.")
            ->setHelp('The <info>composer</info> command updates the composer vendor dependencies needed by Grav');
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

        $action = $input->getOption('install') ? 'install' : ($input->getOption('update') ? 'update' : 'install');

        if ($input->getOption('install')) {
            $action = 'install';
        }

        // Updates composer first
        $output->writeln("\nInstalling vendor dependencies");
        $output->writeln($this->composerUpdate(GRAV_ROOT, $action));
    }

}
