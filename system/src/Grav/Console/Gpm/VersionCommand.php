<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Upgrader;
use Grav\Common\Grav;
use Grav\Console\GpmCommand;
use RocketTheme\Toolbox\File\YamlFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use function count;

/**
 * Class VersionCommand
 * @package Grav\Console\Gpm
 */
class VersionCommand extends GpmCommand
{
    /** @var GPM */
    protected $gpm;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('version')
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
            ->setDescription('Shows the version of an installed package. If available also shows pending updates.')
            ->setHelp('The <info>version</info> command displays the current version of a package installed and, if available, the available version of pending updates');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        $this->gpm = new GPM($input->getOption('force'));
        $packages = $input->getArgument('package');

        $installed = false;

        if (!count($packages)) {
            $packages = ['grav'];
        }

        foreach ($packages as $package) {
            $package = strtolower($package);
            $name = null;
            $version = null;
            $updatable = false;

            if ($package === 'grav') {
                $name = 'Grav';
                $version = GRAV_VERSION;
                $upgrader = new Upgrader();

                if ($upgrader->isUpgradable()) {
                    $updatable = " [upgradable: v<green>{$upgrader->getRemoteVersion()}</green>]";
                }
            } else {
                // get currently installed version
                $locator = Grav::instance()['locator'];
                $blueprints_path = $locator->findResource('plugins://' . $package . DS . 'blueprints.yaml');
                if (!file_exists($blueprints_path)) { // theme?
                    $blueprints_path = $locator->findResource('themes://' . $package . DS . 'blueprints.yaml');
                    if (!file_exists($blueprints_path)) {
                        continue;
                    }
                }

                $file = YamlFile::instance($blueprints_path);
                $package_yaml = $file->content();
                $file->free();

                $version = $package_yaml['version'];

                if (!$version) {
                    continue;
                }

                $installed = $this->gpm->findPackage($package);
                if ($installed) {
                    $name = $installed->name;

                    if ($this->gpm->isUpdatable($package)) {
                        $updatable = " [updatable: v<green>{$installed->available}</green>]";
                    }
                }
            }

            $updatable = $updatable ?: '';

            if ($installed || $package === 'grav') {
                $io->writeln("You are running <white>{$name}</white> v<cyan>{$version}</cyan>{$updatable}");
            } else {
                $io->writeln("Package <red>{$package}</red> not found");
            }
        }

        return 0;
    }
}
