<?php

/**
 * @package    Grav\Console\Gpm
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Console\ConsoleCommand;
use Grav\Common\GPM\Upgrader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateCommand extends ConsoleCommand
{
    /** @var array */
    protected $data;

    protected $extensions;

    protected $updatable;

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

    protected $overwrite;

    /** @var Upgrader */
    protected $upgrader;

    protected function configure()
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

    protected function serve()
    {
        $this->upgrader = new Upgrader($this->input->getOption('force'));
        $local = $this->upgrader->getLocalVersion();
        $remote = $this->upgrader->getRemoteVersion();
        if ($local !== $remote) {
            $this->output->writeln('<yellow>WARNING</yellow>: A new version of Grav is available. You should update Grav before updating plugins and themes. If you continue without updating Grav, some plugins or themes may stop working.');
            $this->output->writeln('');
            $questionHelper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue with the update process? [Y|n] ', true);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln('<red>Update aborted. Exiting...</red>');
                exit;
            }
        }

        $this->gpm = new GPM($this->input->getOption('force'));

        $this->all_yes = $this->input->getOption('all-yes');
        $this->overwrite = $this->input->getOption('overwrite');

        $this->displayGPMRelease();

        $this->destination = realpath($this->input->getOption('destination'));

        if (!Installer::isGravInstance($this->destination)) {
            $this->output->writeln('<red>ERROR</red>: ' . Installer::lastErrorMsg());
            exit;
        }
        if ($this->input->getOption('plugins') === false && $this->input->getOption('themes') === false) {
            $list_type = ['plugins' => true, 'themes' => true];
        } else {
            $list_type['plugins'] = $this->input->getOption('plugins');
            $list_type['themes'] = $this->input->getOption('themes');
        }

        if ($this->overwrite) {
            $this->data = $this->gpm->getInstallable($list_type);
            $description = ' can be overwritten';
        } else {
            $this->data = $this->gpm->getUpdatable($list_type);
            $description = ' need updating';
        }

        $only_packages = array_map('strtolower', $this->input->getArgument('package'));

        if (!$this->overwrite && !$this->data['total']) {
            $this->output->writeln('Nothing to update.');
            exit;
        }

        $this->output->write("Found <green>{$this->gpm->countInstalled()}</green> packages installed of which <magenta>{$this->data['total']}</magenta>{$description}");

        $limit_to = $this->userInputPackages($only_packages);

        $this->output->writeln('');

        unset($this->data['total'], $limit_to['total']);


        // updates review
        $slugs = [];

        $index = 0;
        foreach ($this->data as $packages) {
            foreach ($packages as $slug => $package) {
                if (!array_key_exists($slug, $limit_to) && \count($only_packages)) {
                    continue;
                }

                if (!$package->available) {
                    $package->available = $package->version;
                }

                $this->output->writeln(
                // index
                    str_pad($index++ + 1, 2, '0', STR_PAD_LEFT) . '. ' .
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
            $this->output->writeln('');
            $questionHelper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue with the update process? [Y|n] ', true);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln('<red>Update aborted. Exiting...</red>');
                exit;
            }
        }

        // finally update
        $install_command = $this->getApplication()->find('install');

        $args = new ArrayInput([
            'command' => 'install',
            'package' => $slugs,
            '-f' => $this->input->getOption('force'),
            '-d' => $this->destination,
            '-y' => true
        ]);
        $command_exec = $install_command->run($args, $this->output);

        if ($command_exec != 0) {
            $this->output->writeln('<red>Error:</red> An error occurred while trying to install the packages');
            exit;
        }
    }

    /**
     * @param array $only_packages
     *
     * @return array
     */
    private function userInputPackages($only_packages)
    {
        $found = ['total' => 0];
        $ignore = [];

        if (!\count($only_packages)) {
            $this->output->writeln('');
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
                    $this->output->write(", only <magenta>{$found['total']}</magenta> will be updated");
                }

                $this->output->writeln('');
                $this->output->writeln('Limiting updates for only <cyan>' . implode('</cyan>, <cyan>',
                        $list) . '</cyan>');
            }

            if (\count($ignore)) {
                $this->output->writeln('');
                $this->output->writeln('Packages not found or not requiring updates: <red>' . implode('</red>, <red>',
                        $ignore) . '</red>');

            }
        }

        return $found;
    }
}
