<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;

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
                'The local location or remote URL to an installable package file'
            )
            ->setDescription("Installs Grav, plugin, or theme directly from a file or a URL")
            ->setHelp('The <info>direct-install</info> command installs Grav, plugin, or theme directly from a file or a URL');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $package_file = $this->input->getArgument('package-file');

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to direct-install <cyan>'.$package_file.'</cyan> [y|N] ', false);

        $answer = $helper->ask($this->input, $this->output, $question);

        if (!$answer) {
            $this->output->writeln("exiting...");
            $this->output->writeln('');
            exit;
        }

        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $tmp_zip = $tmp_dir . '/Grav-' . uniqid();

        $this->output->writeln("");
        $this->output->writeln("Preparing to install <cyan>" . $package_file . "</cyan>");


        if ($this->isRemote($package_file)) {
            $zip = $this->downloadPackage($package_file, $tmp_zip);
        } else {
            $zip = $this->copyPackage($package_file, $tmp_zip);
        }

        if (file_exists($zip)) {
            $tmp_source = $tmp_dir . '/Grav-' . uniqid();

            $this->output->write("  |- Extracting package...    ");
            $extracted = Installer::unZip($zip, $tmp_source);

            if (!$extracted) {
                $this->output->write("\x0D");
                $this->output->writeln("  |- Extracting package...    <red>failed</red>");
                exit;
            }

            $this->output->write("\x0D");
            $this->output->writeln("  |- Extracting package...    <green>ok</green>");

            $type = $this->getPackageType($extracted);

            if (!$type) {
                $this->output->writeln("  '- <red>ERROR: Not a valid Grav package</red>");
                $this->output->writeln('');
                exit;
            }

            $blueprint = $this->getBlueprints($extracted);
            if ($blueprint) {
                if (isset($blueprint['dependencies'])) {
                    $depencencies = [];
                    foreach ($blueprint['dependencies'] as $dependency) {
                        if (is_array($dependency) && isset($dependency['name'])) {
                            $depencencies[] = $dependency['name'];
                        } else {
                            $depencencies[] = $dependency;
                        }
                    }
                    $this->output->writeln("  |- Dependencies found...    <cyan>[" . implode(',', $depencencies) . "]</cyan>");



                    $question = new ConfirmationQuestion("  |  '- Dependencies will not be satisfied. Continue ? [y|N] ", false);
                    $answer = $helper->ask($this->input, $this->output, $question);

                    if (!$answer) {
                        $this->output->writeln("exiting...");
                        $this->output->writeln('');
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
                    exit;
                }

                $this->output->write("\x0D");
                $this->output->writeln("  |- Checking destination...  <green>ok</green>");

                $this->output->write("  |- Installing package...  ");
                Installer::install($zip, GRAV_ROOT, ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true], $extracted);
            } else {
                $name = $this->getPackageName($extracted);

                if (!$name) {
                    $this->output->writeln("<red>ERROR: Name could not be determined.</red> Please specify with --name|-n");
                    $this->output->writeln('');
                    exit;
                }

                $install_path = $this->getInstallPath($type, $name);
                $is_update = file_exists($install_path);

                $this->output->write("  |- Checking destination...  ");

                Installer::isValidDestination(GRAV_ROOT . DS . $install_path);
                if (Installer::lastErrorCode() == Installer::IS_LINK) {
                    $this->output->write("\x0D");
                    $this->output->writeln("  |- Checking destination...  <yellow>symbolic link</yellow>");
                    $this->output->writeln("  '- <red>ERROR: symlink found...</red>  <yellow>" . GRAV_ROOT . DS . $install_path . '</yellow>');
                    $this->output->writeln('');
                    exit;

                } else {
                    $this->output->write("\x0D");
                    $this->output->writeln("  |- Checking destination...  <green>ok</green>");
                }

                $this->output->write("  |- Installing package...  ");

                Installer::install($zip, GRAV_ROOT, ['install_path' => $install_path, 'theme' => (($type == 'theme')), 'is_update' => $is_update], $extracted);
            }

            Folder::delete($tmp_source);

            $this->output->write("\x0D");

            if(Installer::lastErrorCode()) {
                $this->output->writeln("  '- <red>Installation failed or aborted.</red>");
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

    /**
     * Get the install path for a name and a particular type of package
     *
     * @param $type
     * @param $name
     * @return string
     */
    protected function getInstallPath($type, $name)
    {
        $locator = Grav::instance()['locator'];

        if ($type == 'theme') {
            $install_path = $locator->findResource('themes://', false) . DS . $name;
        } else {
            $install_path = $locator->findResource('plugins://', false) . DS . $name;
        }
        return $install_path;
    }

    /**
     * Try to guess the package name from the source files
     *
     * @param $source
     * @return bool|string
     */
    protected function getPackageName($source)
    {
        foreach (glob($source . "*.yaml") as $filename) {
            $name = strtolower(basename($filename, '.yaml'));
            if ($name == 'blueprints') {
                continue;
            }
            return $name;
        }
        return false;
    }

    /**
     * Try to guess the package type from the source files
     *
     * @param $source
     * @return bool|string
     */
    protected function getPackageType($source)
    {
        if (
            file_exists($source . 'system/defines.php') &&
            file_exists($source . 'system/config/system.yaml')
        ) {
            return 'grav';
        } else {
            // must have a blueprint
            if (!file_exists($source . 'blueprints.yaml')) {
                return false;
            }

            // either theme or plugin
            $name = basename($source);
            if (Utils::contains($name, 'theme')) {
                return 'theme';
            } elseif (Utils::contains($name, 'plugin')) {
                return 'plugin';
            }
            foreach (glob($source . "*.php") as $filename) {
                $contents = file_get_contents($filename);
                if (Utils::contains($contents, 'Grav\Common\Theme')) {
                    return 'theme';
                } elseif (Utils::contains($contents, 'Grav\Common\Plugin')) {
                    return 'plugin';
                }
            }

            // Assume it's a theme
            return 'theme';
        }
    }

    /**
     * Determine if this is a local or a remote file
     *
     * @param $file
     * @return bool
     */
    protected function isRemote($file)
    {
        return (bool) filter_var($file, FILTER_VALIDATE_URL);
    }

    /**
     * Find/Parse the blueprint file
     *
     * @param $source
     * @return array|bool
     */
    protected function getBlueprints($source)
    {
        $blueprint_file = $source . 'blueprints.yaml';
        if (!file_exists($blueprint_file)) {
            return false;
        }

        $blueprint = (array)Yaml::parse(file_get_contents($blueprint_file));
        return $blueprint;
    }

    /**
     * Download the zip package via the URL
     *
     * @param $package_file
     * @param $tmp
     * @return null|string
     */
    private function downloadPackage($package_file, $tmp)
    {
        $this->output->write("  |- Downloading package...     0%");

        $package = parse_url($package_file);


        $filename = basename($package['path']);
        $output = Response::get($package_file, [], [$this, 'progress']);

        if ($output) {
            Folder::mkdir($tmp);

            $this->output->write("\x0D");
            $this->output->write("  |- Downloading package...   100%");
            $this->output->writeln('');

            file_put_contents($tmp . DS . $filename, $output);

            return $tmp . DS . $filename;
        }

        return null;

    }

    /**
     * Copy the local zip package to tmp
     *
     * @param $package_file
     * @param $tmp
     * @return null|string
     */
    private function copyPackage($package_file, $tmp)
    {
        $this->output->write("  |- Copying package...         0%");

        $package_file = realpath($package_file);

        if (file_exists($package_file)) {
            $filename = basename($package_file);

            Folder::mkdir($tmp);

            $this->output->write("\x0D");
            $this->output->write("  |- Copying package...       100%");
            $this->output->writeln('');

            copy(realpath($package_file), $tmp . DS . $filename);

            return $tmp . DS . $filename;
        }

        return null;

    }

    /**
     * @param $progress
     */
    public function progress($progress)
    {
        $this->output->write("\x0D");
        $this->output->write("  |- Downloading package... " . str_pad($progress['percent'], 5, " ",
                STR_PAD_LEFT) . '%');
    }
}
