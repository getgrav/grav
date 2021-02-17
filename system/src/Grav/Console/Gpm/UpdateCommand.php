<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Upgrader;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZipArchive;
use function array_key_exists;
use function count;

/**
 * Class UpdateCommand
 * @package Grav\Console\Gpm
 */
class UpdateCommand extends GpmCommand
{
    /** @var array */
    protected $data;
    /** @var string */
    protected $destination;
    /** @var string */
    protected $file;
    /** @var array */
    protected $types = ['plugins', 'themes'];
    /** @var GPM  */
    protected $gpm;
    /** @var string */
    protected $all_yes;
    /** @var string */
    protected $overwrite;
    /** @var Upgrader */
    protected $upgrader;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('update')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-fetching the data from remote'
            )
            ->addOption(
                'destination',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The grav instance location where the updates should be applied to. By default this would be where the grav cli has been launched from',
                GRAV_ROOT
            )
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addOption(
                'overwrite',
                'o',
                InputOption::VALUE_NONE,
                'Option to overwrite packages if they already exist'
            )
            ->addOption(
                'plugins',
                'p',
                InputOption::VALUE_NONE,
                'Update only plugins'
            )
            ->addOption(
                'themes',
                't',
                InputOption::VALUE_NONE,
                'Update only themes'
            )
            ->addArgument(
                'package',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The package or packages that is desired to update. By default all available updates will be applied.'
            )
            ->setDescription('Detects and performs an update of plugins and themes when available')
            ->setHelp('The <info>update</info> command updates plugins and themes when a new version is available');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        if (!class_exists(ZipArchive::class)) {
            $io->title('GPM Update');
            $io->error('php-zip extension needs to be enabled!');

            return 1;
        }

        $this->upgrader = new Upgrader($input->getOption('force'));
        $local = $this->upgrader->getLocalVersion();
        $remote = $this->upgrader->getRemoteVersion();
        if ($local !== $remote) {
            $io->writeln('<yellow>WARNING</yellow>: A new version of Grav is available. You should update Grav before updating plugins and themes. If you continue without updating Grav, some plugins or themes may stop working.');
            $io->newLine();
            $question = new ConfirmationQuestion('Continue with the update process? [Y|n] ', true);
            $answer = $io->askQuestion($question);

            if (!$answer) {
                $io->writeln('<red>Update aborted. Exiting...</red>');

                return 1;
            }
        }

        $this->gpm = new GPM($input->getOption('force'));

        $this->all_yes = $input->getOption('all-yes');
        $this->overwrite = $input->getOption('overwrite');

        $this->displayGPMRelease();

        $this->destination = realpath($input->getOption('destination'));

        if (!Installer::isGravInstance($this->destination)) {
            $io->writeln('<red>ERROR</red>: ' . Installer::lastErrorMsg());
            exit;
        }
        if ($input->getOption('plugins') === false && $input->getOption('themes') === false) {
            $list_type = ['plugins' => true, 'themes' => true];
        } else {
            $list_type['plugins'] = $input->getOption('plugins');
            $list_type['themes'] = $input->getOption('themes');
        }

        if ($this->overwrite) {
            $this->data = $this->gpm->getInstallable($list_type);
            $description = ' can be overwritten';
        } else {
            $this->data = $this->gpm->getUpdatable($list_type);
            $description = ' need updating';
        }

        $only_packages = array_map('strtolower', $input->getArgument('package'));

        if (!$this->overwrite && !$this->data['total']) {
            $io->writeln('Nothing to update.');

            return 0;
        }

        $io->write("Found <green>{$this->gpm->countInstalled()}</green> packages installed of which <magenta>{$this->data['total']}</magenta>{$description}");

        $limit_to = $this->userInputPackages($only_packages);

        $io->newLine();

        unset($this->data['total'], $limit_to['total']);


        // updates review
        $slugs = [];

        $index = 1;
        foreach ($this->data as $packages) {
            foreach ($packages as $slug => $package) {
                if (!array_key_exists($slug, $limit_to) && count($only_packages)) {
                    continue;
                }

                if (!$package->available) {
                    $package->available = $package->version;
                }

                $io->writeln(
                    // index
                    str_pad((string)$index++, 2, '0', STR_PAD_LEFT) . '. ' .
                    // name
                    '<cyan>' . str_pad($package->name, 15) . '</cyan> ' .
                    // version
                    "[v<magenta>{$package->version}</magenta> -> v<green>{$package->available}</green>]"
                );
                $slugs[] = $slug;
            }
        }

        if (!$this->all_yes) {
            // prompt to continue
            $io->newLine();
            $question = new ConfirmationQuestion('Continue with the update process? [Y|n] ', true);
            $answer = $io->askQuestion($question);

            if (!$answer) {
                $io->writeln('<red>Update aborted. Exiting...</red>');

                return 1;
            }
        }

        // finally update
        $install_command = $this->getApplication()->find('install');

        $args = new ArrayInput([
            'command' => 'install',
            'package' => $slugs,
            '-f' => $input->getOption('force'),
            '-d' => $this->destination,
            '-y' => true
        ]);
        $command_exec = $install_command->run($args, $io);

        if ($command_exec != 0) {
            $io->writeln('<red>Error:</red> An error occurred while trying to install the packages');

            return 1;
        }

        return 0;
    }

    /**
     * @param array $only_packages
     * @return array
     */
    private function userInputPackages(array $only_packages): array
    {
        $io = $this->getIO();

        $found = ['total' => 0];
        $ignore = [];

        if (!count($only_packages)) {
            $io->newLine();
        } else {
            foreach ($only_packages as $only_package) {
                $find = $this->gpm->findPackage($only_package);

                if (!$find || (!$this->overwrite && !$this->gpm->isUpdatable($find->slug))) {
                    $name = $find->slug ?? $only_package;
                    $ignore[$name] = $name;
                } else {
                    $found[$find->slug] = $find;
                    $found['total']++;
                }
            }

            if ($found['total']) {
                $list = $found;
                unset($list['total']);
                $list = array_keys($list);

                if ($found['total'] !== $this->data['total']) {
                    $io->write(", only <magenta>{$found['total']}</magenta> will be updated");
                }

                $io->newLine();
                $io->writeln('Limiting updates for only <cyan>' . implode(
                    '</cyan>, <cyan>',
                    $list
                ) . '</cyan>');
            }

            if (count($ignore)) {
                $io->newLine();
                $io->writeln('Packages not found or not requiring updates: <red>' . implode(
                    '</red>, <red>',
                    $ignore
                ) . '</red>');
            }
        }

        return $found;
    }
}
