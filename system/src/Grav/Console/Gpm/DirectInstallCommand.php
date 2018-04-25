<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DirectInstallCommand extends ConsoleCommand
{

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("direct-install")
            ->setAliases(['directinstall'])
            ->addArgument(
                'package-file',
                InputArgument::REQUIRED,
                'Installable package local <path> or remote <URL>. Can install specific version'
            )
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addOption(
                'destination',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The destination where the package should be installed at. By default this would be where the grav instance has been launched from',
                GRAV_ROOT
            )
            ->setDescription("Installs Grav, plugin, or theme directly from a file or a URL")
            ->setHelp('The <info>direct-install</info> command installs Grav, plugin, or theme directly from a file or a URL');
    }

    /**
     * @return bool
     */
    protected function serve()
    {
        // Making sure the destination is usable
        $this->destination = realpath($this->input->getOption('destination'));

        if (
            !Installer::isGravInstance($this->destination) ||
            !Installer::isValidDestination($this->destination, [Installer::EXISTS, Installer::IS_LINK])
        ) {
            $this->output->writeln("<red>ERROR</red>: " . Installer::lastErrorMsg());
            exit;
        }


        $this->all_yes = $this->input->getOption('all-yes');

        $package_file = $this->input->getArgument('package-file');

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to direct-install <cyan>'.$package_file.'</cyan> [y|N] ', false);

        $answer = $this->all_yes ? true : $helper->ask($this->input, $this->output, $question);

        if (!$answer) {
            $this->output->writeln("exiting...");
            $this->output->writeln('');
            exit;
        }

        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $tmp_zip = $tmp_dir . '/Grav-' . uniqid();

        $this->output->writeln("");
        $this->output->writeln("Preparing to install <cyan>" . $package_file . "</cyan>");


        if (Response::isRemote($package_file)) {
            $this->output->write("  |- Downloading package...     0%");
            try {
                $zip = GPM::downloadPackage($package_file, $tmp_zip);
            } catch (\RuntimeException $e) {
                $this->output->writeln('');
                $this->output->writeln("  `- <red>ERROR: " . $e->getMessage() . "</red>");
                $this->output->writeln('');
                exit;
            }

            if ($zip) {
                $this->output->write("\x0D");
                $this->output->write("  |- Downloading package...   100%");
                $this->output->writeln('');
            }
        } else {
            $this->output->write("  |- Copying package...         0%");
            $zip = GPM::copyPackage($package_file, $tmp_zip);
            if ($zip) {
                $this->output->write("\x0D");
                $this->output->write("  |- Copying package...       100%");
                $this->output->writeln('');
            }
        }

        if (file_exists($zip)) {
            $tmp_source = $tmp_dir . '/Grav-' . uniqid();

            $this->output->write("  |- Extracting package...    ");
            $extracted = Installer::unZip($zip, $tmp_source);

            if (!$extracted) {
                $this->output->write("\x0D");
                $this->output->writeln("  |- Extracting package...    <red>failed</red>");
                Folder::delete($tmp_source);
                Folder::delete($tmp_zip);
                exit;
            }

            $this->output->write("\x0D");
            $this->output->writeln("  |- Extracting package...    <green>ok</green>");


            $type = GPM::getPackageType($extracted);

            if (!$type) {
                $this->output->writeln("  '- <red>ERROR: Not a valid Grav package</red>");
                $this->output->writeln('');
                Folder::delete($tmp_source);
                Folder::delete($tmp_zip);
                exit;
            }

            $blueprint = GPM::getBlueprints($extracted);
            if ($blueprint) {
                if (isset($blueprint['dependencies'])) {
                    $depencencies = [];
                    foreach ($blueprint['dependencies'] as $dependency) {
                        if (is_array($dependency)){
                           if (isset($dependency['name'])) {
                              $depencencies[] = $dependency['name'];
                           }
                           if (isset($dependency['github'])) {
                              $depencencies[] = $dependency['github'];
                           }
                        } else {
                           $depencencies[] = $dependency;
                        }
                    }
                    $this->output->writeln("  |- Dependencies found...    <cyan>[" . implode(',', $depencencies) . "]</cyan>");

                    $question = new ConfirmationQuestion("  |  '- Dependencies will not be satisfied. Continue ? [y|N] ", false);
                    $answer = $this->all_yes ? true : $helper->ask($this->input, $this->output, $question);

                    if (!$answer) {
                        $this->output->writeln("exiting...");
                        $this->output->writeln('');
                        Folder::delete($tmp_source);
                        Folder::delete($tmp_zip);
                        exit;
                    }
                }
            }

            if ($type == 'grav') {

                $this->output->write("  |- Checking destination...  ");
                Installer::isValidDestination(GRAV_ROOT . '/system');
                if (Installer::IS_LINK === Installer::lastErrorCode()) {
                    $this->output->write("\x0D");
                    $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");
                    $this->output->writeln("  '- <red>ERROR: symlinks found...</red> <yellow>" . GRAV_ROOT."</yellow>");
                    $this->output->writeln('');
                    Folder::delete($tmp_source);
                    Folder::delete($tmp_zip);
                    exit;
                }

                $this->output->write("\x0D");
                $this->output->writeln("  |- Checking destination...  <green>ok</green>");

                $this->output->write("  |- Installing package...  ");
                Installer::install($zip, GRAV_ROOT, ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true], $extracted);
            } else {
                $name = GPM::getPackageName($extracted);

                if (!$name) {
                    $this->output->writeln("<red>ERROR: Name could not be determined.</red> Please specify with --name|-n");
                    $this->output->writeln('');
                    Folder::delete($tmp_source);
                    Folder::delete($tmp_zip);
                    exit;
                }

                $install_path = GPM::getInstallPath($type, $name);
                $is_update = file_exists($install_path);

                $this->output->write("  |- Checking destination...  ");

                Installer::isValidDestination(GRAV_ROOT . DS . $install_path);
                if (Installer::lastErrorCode() == Installer::IS_LINK) {
                    $this->output->write("\x0D");
                    $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");
                    $this->output->writeln("  '- <red>ERROR: symlink found...</red>  <yellow>" . GRAV_ROOT . DS . $install_path . '</yellow>');
                    $this->output->writeln('');
                    Folder::delete($tmp_source);
                    Folder::delete($tmp_zip);
                    exit;

                } else {
                    $this->output->write("\x0D");
                    $this->output->writeln("  |- Checking destination...  <green>ok</green>");
                }

                $this->output->write("  |- Installing package...  ");

                Installer::install(
                    $zip,
                    $this->destination,
                    $options = [
                        'install_path' => $install_path,
                        'theme' => (($type == 'theme')),
                        'is_update' => $is_update
                    ],
                    $extracted
                );
            }

            Folder::delete($tmp_source);

            $this->output->write("\x0D");

            if(Installer::lastErrorCode()) {
                $this->output->writeln("  '- <red>" . Installer::lastErrorMsg() . "</red>");
                $this->output->writeln('');
            } else {
                $this->output->writeln("  |- Installing package...    <green>ok</green>");
                $this->output->writeln("  '- <green>Success!</green>  ");
                $this->output->writeln('');
            }

        } else {
            $this->output->writeln("  '- <red>ERROR: ZIP package could not be found</red>");
        }

        Folder::delete($tmp_zip);

        // clear cache after successful upgrade
        $this->clearCache();

        return true;

    }
}
