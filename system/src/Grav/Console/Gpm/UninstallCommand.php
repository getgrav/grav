<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UninstallCommand extends ConsoleCommand
{
    /**
     * @var
     */
    protected $data;

    /** @var GPM */
    protected $gpm;

    /**
     * @var
     */
    protected $destination;
    /**
     * @var
     */
    protected $file;
    /**
     * @var
     */
    protected $tmp;

    protected $dependencies= [];

    protected $all_yes;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName("uninstall")
            ->addOption(
                'all-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assumes yes (or best approach) instead of prompting'
            )
            ->addArgument(
                'package',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'The package(s) that are desired to be removed. Use the "index" command for a list of packages'
            )
            ->setDescription("Performs the uninstallation of plugins and themes")
            ->setHelp('The <info>uninstall</info> command allows to uninstall plugins and themes');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->gpm = new GPM();

        $this->all_yes = $this->input->getOption('all-yes');

        $packages = array_map('strtolower', $this->input->getArgument('package'));
        $this->data = ['total' => 0, 'not_found' => []];

        foreach ($packages as $package) {
            $plugin = $this->gpm->getInstalledPlugin($package);
            $theme = $this->gpm->getInstalledTheme($package);
            if ($plugin || $theme) {
                $this->data[strtolower($package)] = $plugin ?: $theme;
                $this->data['total']++;
            } else {
                $this->data['not_found'][] = $package;
            }
        }

        $this->output->writeln('');

        if (!$this->data['total']) {
            $this->output->writeln("Nothing to uninstall.");
            $this->output->writeln('');
            exit;
        }

        if (count($this->data['not_found'])) {
            $this->output->writeln("These packages were not found installed: <red>" . implode('</red>, <red>',
                    $this->data['not_found']) . "</red>");
        }

        unset($this->data['not_found']);
        unset($this->data['total']);

        foreach ($this->data as $slug => $package) {
            $this->output->writeln("Preparing to uninstall <cyan>" . $package->name . "</cyan> [v" . $package->version . "]");

            $this->output->write("  |- Checking destination...  ");
            $checks = $this->checkDestination($slug, $package);

            if (!$checks) {
                $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
                $this->output->writeln('');
            } else {
                $uninstall = $this->uninstallPackage($slug, $package);

                if (!$uninstall) {
                    $this->output->writeln("  '- <red>Uninstallation failed or aborted.</red>");
                } else {
                    $this->output->writeln("  '- <green>Success!</green>  ");
                }
            }

        }

        // clear cache after successful upgrade
        $this->clearCache();
    }


    /**
     * @param $slug
     * @param $package
     *
     * @return bool
     */
    private function uninstallPackage($slug, $package, $is_dependency = false)
    {
        if (!$slug) {
            return false;
        }

        //check if there are packages that have this as a dependency. Abort and show list
        $dependent_packages = $this->gpm->getPackagesThatDependOnPackage($slug);
        if (count($dependent_packages) > ($is_dependency ? 1 : 0)) {
            $this->output->writeln('');
            $this->output->writeln('');
            $this->output->writeln("<red>Uninstallation failed.</red>");
            $this->output->writeln('');
            if (count($dependent_packages) > ($is_dependency ? 2 : 1)) {
                $this->output->writeln("The installed packages <cyan>" . implode('</cyan>, <cyan>', $dependent_packages) . "</cyan> depends on this package. Please remove those first.");
            } else {
                $this->output->writeln("The installed package <cyan>" . implode('</cyan>, <cyan>', $dependent_packages) . "</cyan> depends on this package. Please remove it first.");
            }

            $this->output->writeln('');
            return false;
        }

        if (isset($package->dependencies)) {

            $dependencies = $package->dependencies;

            if ($is_dependency) {
                foreach ($dependencies as $key => $dependency) {
                    if (in_array($dependency['name'], $this->dependencies)) {
                        unset($dependencies[$key]);
                    }
                }
            } else {
                if (count($dependencies) > 0) {
                    $this->output->writeln('  `- Dependencies found...');
                    $this->output->writeln('');
                }
            }

            $questionHelper = $this->getHelper('question');

            foreach ($dependencies as $dependency) {

                $this->dependencies[] = $dependency['name'];

                if (is_array($dependency)) {
                    $dependency = $dependency['name'];
                }
                if ($dependency === 'grav' || $dependency === 'php') {
                    continue;
                }

                $dependencyPackage = $this->gpm->findPackage($dependency);

                $dependency_exists = $this->packageExists($dependency, $dependencyPackage);

                if ($dependency_exists == Installer::EXISTS) {
                    $this->output->writeln("A dependency on <cyan>" . $dependencyPackage->name . "</cyan> [v" . $dependencyPackage->version . "] was found");

                    $question = new ConfirmationQuestion("  |- Uninstall <cyan>" . $dependencyPackage->name . "</cyan>? [y|N] ", false);
                    $answer = $this->all_yes ? true : $questionHelper->ask($this->input, $this->output, $question);

                    if ($answer) {
                        $uninstall = $this->uninstallPackage($dependency, $dependencyPackage, true);

                        if (!$uninstall) {
                            $this->output->writeln("  '- <red>Uninstallation failed or aborted.</red>");
                        } else {
                            $this->output->writeln("  '- <green>Success!</green>  ");

                        }
                        $this->output->writeln('');
                    } else {
                        $this->output->writeln("  '- <yellow>You decided not to uninstall " . $dependencyPackage->name . ".</yellow>");
                        $this->output->writeln('');
                    }
                }

            }
        }


        $locator = Grav::instance()['locator'];
        $path = $locator->findResource($package->package_type . '://' . $slug);
        Installer::uninstall($path);
        $errorCode = Installer::lastErrorCode();

        if ($errorCode && $errorCode !== Installer::IS_LINK && $errorCode !== Installer::EXISTS) {
            $this->output->writeln("  |- Uninstalling " . $package->name . " package...  <red>error</red>                             ");
            $this->output->writeln("  |  '- <yellow>" . Installer::lastErrorMsg()."</yellow>");

            return false;
        }

        $message = Installer::getMessage();
        if ($message) {
            $this->output->writeln("  |- " . $message);
        }

        if (!$is_dependency && $this->dependencies) {
            $this->output->writeln("Finishing up uninstalling <cyan>" . $package->name . "</cyan>");
        }
        $this->output->writeln("  |- Uninstalling " . $package->name . " package...  <green>ok</green>                             ");



        return true;
    }

    /**
     * @param $slug
     * @param $package
     *
     * @return bool
     */

    private function checkDestination($slug, $package)
    {
        $questionHelper = $this->getHelper('question');

        $exists = $this->packageExists($slug, $package);

        if ($exists == Installer::IS_LINK) {
            $this->output->write("\x0D");
            $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");

            if ($this->all_yes) {
                $this->output->writeln("  |     '- <yellow>Skipped automatically.</yellow>");

                return false;
            }

            $question = new ConfirmationQuestion("  |  '- Destination has been detected as symlink, delete symbolic link first? [y|N] ",
                false);
            $answer = $this->all_yes ? true : $questionHelper->ask($this->input, $this->output, $question);

            if (!$answer) {
                $this->output->writeln("  |     '- <red>You decided not to delete the symlink automatically.</red>");

                return false;
            }
        }

        $this->output->write("\x0D");
        $this->output->writeln("  |- Checking destination...  <green>ok</green>");

        return true;
    }

    /**
     * Check if package exists
     *
     * @param $slug
     * @param $package
     * @return int
     */
    private function packageExists($slug, $package)
    {
        $path = Grav::instance()['locator']->findResource($package->package_type . '://' . $slug);
        Installer::isValidDestination($path);
        return Installer::lastErrorCode();
    }
}
