<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateCommand extends ConsoleCommand
{
    /**
     * @var
     */
    protected $data;
    /**
     * @var
     */
    protected $extensions;
    /**
     * @var
     */
    protected $updatable;
    /**
     * @var
     */
    protected $destination;
    /**
     * @var
     */
    protected $file;
    /**
     * @var array
     */
    protected $types = ['plugins', 'themes'];
    /**
     * @var GPM $gpm
     */
    protected $gpm;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("update")
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
            ->setDescription("Detects and performs an update of plugins and themes when available")
            ->setHelp('The <info>update</info> command updates plugins and themes when a new version is available');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->gpm = new GPM($this->input->getOption('force'));

        $this->displayGPMRelease();

        $this->destination = realpath($this->input->getOption('destination'));
        $skip_prompt = $this->input->getOption('all-yes');

        if (!Installer::isGravInstance($this->destination)) {
            $this->output->writeln("<red>ERROR</red>: " . Installer::lastErrorMsg());
            exit;
        }
        if ($this->input->getOption('plugins') === false and $this->input->getOption('themes') === false) {
            $list_type_update = ['plugins' => true, 'themes' => true];
        } else {
            $list_type_update['plugins'] = $this->input->getOption('plugins');
            $list_type_update['themes'] = $this->input->getOption('themes');
        }

        $this->data = $this->gpm->getUpdatable($list_type_update);
        $only_packages = array_map('strtolower', $this->input->getArgument('package'));

        if (!$this->data['total']) {
            $this->output->writeln("Nothing to update.");
            exit;
        }

        $this->output->write("Found <green>" . $this->gpm->countInstalled() . "</green> extensions installed of which <magenta>" . $this->data['total'] . "</magenta> need updating");

        $limit_to = $this->userInputPackages($only_packages);

        $this->output->writeln('');

        unset($this->data['total']);
        unset($limit_to['total']);


        // updates review
        $slugs = [];

        $index = 0;
        foreach ($this->data as $packages) {
            foreach ($packages as $slug => $package) {
                if (count($limit_to) && !array_key_exists($slug, $limit_to)) {
                    continue;
                }

                $this->output->writeln(
                // index
                    str_pad($index++ + 1, 2, '0', STR_PAD_LEFT) . ". " .
                    // name
                    "<cyan>" . str_pad($package->name, 15) . "</cyan> " .
                    // version
                    "[v<magenta>" . $package->version . "</magenta> ➜ v<green>" . $package->available . "</green>]"
                );
                $slugs[] = $slug;
            }
        }

        if (!$skip_prompt) {
            // prompt to continue
            $this->output->writeln("");
            $questionHelper = $this->getHelper('question');
            $question = new ConfirmationQuestion("Continue with the update process? [Y|n] ", true);
            $answer = $questionHelper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln("Update aborted. Exiting...");
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
            $this->output->writeln("<red>Error:</red> An error occurred while trying to install the extensions");
            exit;
        }
    }

    /**
     * @param $only_packages
     *
     * @return array
     */
    private function userInputPackages($only_packages)
    {
        $found = ['total' => 0];
        $ignore = [];

        if (!count($only_packages)) {
            $this->output->writeln('');
        } else {
            foreach ($only_packages as $only_package) {
                $find = $this->gpm->findPackage($only_package);

                if (!$find || !$this->gpm->isUpdatable($find->slug)) {
                    $name = isset($find->slug) ? $find->slug : $only_package;
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
                    $this->output->write(", only <magenta>" . $found['total'] . "</magenta> will be updated");
                }

                $this->output->writeln('');
                $this->output->writeln("Limiting updates for only <cyan>" . implode('</cyan>, <cyan>',
                        $list) . "</cyan>");
            }

            if (count($ignore)) {
                $this->output->writeln('');
                $this->output->writeln("Packages not found or not requiring updates: <red>" . implode('</red>, <red>',
                        $ignore) . "</red>");
            }
        }

        return $found;
    }
}
