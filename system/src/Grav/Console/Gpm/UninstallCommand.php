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
use Grav\Common\GPM\Local;
use Grav\Common\GPM\Remote;
use Grav\Common\Grav;
use Grav\Console\GpmCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;
use function count;
use function in_array;
use function is_array;

/**
 * Class UninstallCommand
 * @package Grav\Console\Gpm
 */
class UninstallCommand extends GpmCommand
{
    /** @var array */
    protected $data;
    /** @var GPM */
    protected $gpm;
    /** @var string */
    protected $destination;
    /** @var string */
    protected $file;
    /** @var string */
    protected $tmp;
    /** @var array */
    protected $dependencies = [];
    /** @var string */
    protected $all_yes;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('uninstall')
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
            ->setDescription('Performs the uninstallation of plugins and themes')
            ->setHelp('The <info>uninstall</info> command allows to uninstall plugins and themes');
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $input = $this->getInput();
        $io = $this->getIO();

        $this->gpm = new GPM();

        $this->all_yes = $input->getOption('all-yes');

        $packages = array_map('strtolower', $input->getArgument('package'));
        $this->data = ['total' => 0, 'not_found' => []];

        $total = 0;
        foreach ($packages as $package) {
            $plugin = $this->gpm->getInstalledPlugin($package);
            $theme = $this->gpm->getInstalledTheme($package);
            if ($plugin || $theme) {
                $this->data[strtolower($package)] = $plugin ?: $theme;
                $total++;
            } else {
                $this->data['not_found'][] = $package;
            }
        }
        $this->data['total'] = $total;

        $io->newLine();

        if (!$this->data['total']) {
            $io->writeln('Nothing to uninstall.');
            $io->newLine();

            return 0;
        }

        if (count($this->data['not_found'])) {
            $io->writeln('These packages were not found installed: <red>' . implode(
                '</red>, <red>',
                $this->data['not_found']
            ) . '</red>');
        }

        unset($this->data['not_found'], $this->data['total']);

        // Plugins need to be initialized in order to make clearcache to work.
        try {
            $this->initializePlugins();
        } catch (Throwable $e) {
            $io->writeln("<red>Some plugins failed to initialize: {$e->getMessage()}</red>");
        }

        $error = 0;
        foreach ($this->data as $slug => $package) {
            $io->writeln("Preparing to uninstall <cyan>{$package->name}</cyan> [v{$package->version}]");

            $io->write('  |- Checking destination...  ');
            $checks = $this->checkDestination($slug, $package);

            if (!$checks) {
                $io->writeln("  '- <red>Installation failed or aborted.</red>");
                $io->newLine();
                $error = 1;
            } else {
                $uninstall = $this->uninstallPackage($slug, $package);

                if (!$uninstall) {
                    $io->writeln("  '- <red>Uninstallation failed or aborted.</red>");
                    $error = 1;
                } else {
                    $io->writeln("  '- <green>Success!</green>  ");
                }
            }
        }

        // clear cache after successful upgrade
        $this->clearCache();

        return $error;
    }

    /**
     * @param string $slug
     * @param Local\Package|Remote\Package $package
     * @param bool $is_dependency
     * @return bool
     */
    private function uninstallPackage($slug, $package, $is_dependency = false): bool
    {
        $io = $this->getIO();

        if (!$slug) {
            return false;
        }

        //check if there are packages that have this as a dependency. Abort and show list
        $dependent_packages = $this->gpm->getPackagesThatDependOnPackage($slug);
        if (count($dependent_packages) > ($is_dependency ? 1 : 0)) {
            $io->newLine(2);
            $io->writeln('<red>Uninstallation failed.</red>');
            $io->newLine();
            if (count($dependent_packages) > ($is_dependency ? 2 : 1)) {
                $io->writeln('The installed packages <cyan>' . implode('</cyan>, <cyan>', $dependent_packages) . '</cyan> depends on this package. Please remove those first.');
            } else {
                $io->writeln('The installed package <cyan>' . implode('</cyan>, <cyan>', $dependent_packages) . '</cyan> depends on this package. Please remove it first.');
            }

            $io->newLine();
            return false;
        }

        if (isset($package->dependencies)) {
            $dependencies = $package->dependencies;

            if ($is_dependency) {
                foreach ($dependencies as $key => $dependency) {
                    if (in_array($dependency['name'], $this->dependencies, true)) {
                        unset($dependencies[$key]);
                    }
                }
            } elseif (count($dependencies) > 0) {
                $io->writeln('  `- Dependencies found...');
                $io->newLine();
            }

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
                    $io->writeln("A dependency on <cyan>{$dependencyPackage->name}</cyan> [v{$dependencyPackage->version}] was found");

                    $question = new ConfirmationQuestion("  |- Uninstall <cyan>{$dependencyPackage->name}</cyan>? [y|N] ", false);
                    $answer = $this->all_yes ? true : $io->askQuestion($question);

                    if ($answer) {
                        $uninstall = $this->uninstallPackage($dependency, $dependencyPackage, true);

                        if (!$uninstall) {
                            $io->writeln("  '- <red>Uninstallation failed or aborted.</red>");
                        } else {
                            $io->writeln("  '- <green>Success!</green>  ");
                        }
                        $io->newLine();
                    } else {
                        $io->writeln("  '- <yellow>You decided not to uninstall {$dependencyPackage->name}.</yellow>");
                        $io->newLine();
                    }
                }
            }
        }


        $locator = Grav::instance()['locator'];
        $path = $locator->findResource($package->package_type . '://' . $slug);
        Installer::uninstall($path);
        $errorCode = Installer::lastErrorCode();

        if ($errorCode && $errorCode !== Installer::IS_LINK && $errorCode !== Installer::EXISTS) {
            $io->writeln("  |- Uninstalling {$package->name} package...  <red>error</red>                             ");
            $io->writeln("  |  '- <yellow>" . Installer::lastErrorMsg() . '</yellow>');

            return false;
        }

        $message = Installer::getMessage();
        if ($message) {
            $io->writeln("  |- {$message}");
        }

        if (!$is_dependency && $this->dependencies) {
            $io->writeln("Finishing up uninstalling <cyan>{$package->name}</cyan>");
        }
        $io->writeln("  |- Uninstalling {$package->name} package...  <green>ok</green>                             ");

        return true;
    }

    /**
     * @param string $slug
     * @param Local\Package|Remote\Package $package
     * @return bool
     */
    private function checkDestination(string $slug, $package): bool
    {
        $io = $this->getIO();

        $exists = $this->packageExists($slug, $package);

        if ($exists === Installer::IS_LINK) {
            $io->write("\x0D");
            $io->writeln('  |- Checking destination...  <yellow>symbolic link</yellow>');

            if ($this->all_yes) {
                $io->writeln("  |     '- <yellow>Skipped automatically.</yellow>");

                return false;
            }

            $question = new ConfirmationQuestion(
                "  |  '- Destination has been detected as symlink, delete symbolic link first? [y|N] ",
                false
            );

            $answer = $io->askQuestion($question);
            if (!$answer) {
                $io->writeln("  |     '- <red>You decided not to delete the symlink automatically.</red>");

                return false;
            }
        }

        $io->write("\x0D");
        $io->writeln('  |- Checking destination...  <green>ok</green>');

        return true;
    }

    /**
     * Check if package exists
     *
     * @param string $slug
     * @param Local\Package|Remote\Package $package
     * @return int
     */
    private function packageExists(string $slug, $package): int
    {
        $path = Grav::instance()['locator']->findResource($package->package_type . '://' . $slug);
        Installer::isValidDestination($path);

        return Installer::lastErrorCode();
    }
}
