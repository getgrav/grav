<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Gpm;

use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\GPM\Response;
use Grav\Common\GPM\Remote\Package as Package;

use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DirectInstallCommand extends ConsoleCommand
{

    protected $tmp;

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

        if ($this->isRemote($package_file)) {
            $local_file = $this->downloadPackage($package_file);
        } else {
            $local_file = $this->copyPackage($package_file);
        }

        if (file_exists($local_file)) {
            $this->output->writeln($local_file);
            $extracted_location = $this->unzipPackage($local_file);

            $type = $this->getPackageType($extracted_location);

            if ($type == 'grav') {
                Installer::install($extracted_location, GRAV_ROOT, ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true]);
            } else {
//                $destination =
            }

            // if Grav:
            //Installer::install($this->file, GRAV_ROOT, ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true]);
            // If Theme or Plugin
            //Installer::install($this->file, $this->destination, ['install_path' => $package->install_path, 'theme' => (($type == 'themes')), 'is_update' => $is_update]);
            $error_code = Installer::lastErrorCode();
            Folder::delete($local_file);


        } else {
            // error about jacked up local file
        }


    }

    protected function getPackageType($source)
    {
        if (
            file_exists($source . '/system/defines.php') &&
            file_exists($source . '/system/config/system.yaml')
        ) {
            return 'grav';
        } else {
            // either theme or plugin
        }
    }

    protected function isRemote($file)
    {
        if (filter_var($file, FILTER_VALIDATE_URL))
        {
            return true;
        } else {
            return false;
        }
    }

    private function unzipPackage($zipfile)
    {
        $zip = new \ZipArchive();
        $archive = $zip->open($zipfile);


        if ($archive !== true) {
            self::$error = 'Couldn\'t open ZIP file';

            return false;
        }

        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $tmp = $tmp_dir . '/Grav-' . uniqid();

        Folder::mkdir($tmp);

        $unzip = $zip->extractTo($tmp);

        if (!$unzip) {
            self::$error = 'Extraction of ZIP failed';
            $zip->close();
            Folder::delete($tmp);

            return false;
        }

        $package_folder_name = $zip->getNameIndex(0);
        $installer_file_folder = $tmp . '/' . $package_folder_name;

        return $installer_file_folder;
    }

    private function downloadPackage($package_file)
    {
        $package = parse_url($package_file);

        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $this->tmp = $tmp_dir . '/Grav-' . uniqid();
        $filename = basename($package['path']);
        $output = Response::get($package_file, [], [$this, 'progress']);

        if ($output) {
            Folder::mkdir($this->tmp);

            $this->output->write("\x0D");
            $this->output->write("  |- Downloading package...   100%");
            $this->output->writeln('');

            file_put_contents($this->tmp . DS . $filename, $output);

            return $this->tmp . DS . $filename;
        }

        return null;

    }

    private function copyPackage($package_file)
    {
        $package_file = realpath($package_file);

        if (file_exists($package_file)) {
            $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
            $this->tmp = $tmp_dir . '/Grav-' . uniqid();
            $filename = basename($package_file);

            Folder::mkdir($this->tmp);

            $this->output->write("\x0D");
            $this->output->write("  |- Downloading package...   100%");
            $this->output->writeln('');

            copy(realpath($package_file), $this->tmp . DS . $filename);

            return $this->tmp . DS . $filename;
        }

        return null;

    }
}
