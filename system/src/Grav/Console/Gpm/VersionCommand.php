<?php
namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Upgrader;
use Grav\Console\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class VersionCommand
 * @package Grav\Console\Gpm
 */
class VersionCommand extends Command
{
    use ConsoleTrait;

    /**
     * @var
     */
    protected $gpm;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("version")
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-fetching the data from remote'
            )
            ->addArgument(
                'package',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The package or packages that is desired to know the version of. By default and if not specified this would be grav'
            )
            ->setDescription("Shows the version of an installed package. If available also shows pending updates.")
            ->setHelp('The <info>version</info> command displays the current version of a package installed and, if available, the available version of pending updates');
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

        $this->gpm = new GPM($this->input->getOption('force'));
        $packages = $this->input->getArgument('package');

        $installed = false;

        if (!count($packages)) {
            $packages = ['grav'];
        }

        foreach ($packages as $package) {
            $package = strtolower($package);
            $name = null;
            $version = null;
            $updatable = false;

            if ($package == 'grav') {
                $name = 'Grav';
                $version = GRAV_VERSION;
                $upgrader = new Upgrader();

                if ($upgrader->isUpgradable()) {
                    $updatable = ' [upgradable: v<green>' . $upgrader->getRemoteVersion() . '</green>]';
                }

            } else {
                if ($installed = $this->gpm->findPackage($package)) {
                    $name = $installed->name;
                    $version = $installed->version;

                    if ($this->gpm->isUpdatable($package)) {
                        $updatable = ' [updatable: v<green>' . $installed->available . '</green>]';
                    }
                }
            }

            $updatable = $updatable ?: '';

            if ($installed || $package == 'grav') {
                $this->output->writeln('You are running <white>' . $name . '</white> v<cyan>' . $version . '</cyan>' . $updatable);
            } else {
                $this->output->writeln('Package <red>' . $package . '</red> not found');
            }
        }
    }
}
