<?php
namespace Grav\Console\Cli;

use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ComposerCommand
 * @package Grav\Console\Cli
 */
class ComposerCommand extends ConsoleCommand
{
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
     * @return int|null|void
     */
    protected function serve()
    {
        $action = $this->input->getOption('install') ? 'install' : ($this->input->getOption('update') ? 'update' : 'install');

        if ($this->input->getOption('install')) {
            $action = 'install';
        }

        // Updates composer first
        $this->output->writeln("\nInstalling vendor dependencies");
        $this->output->writeln($this->composerUpdate(GRAV_ROOT, $action));
    }

}
