<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use DirectoryIterator;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use RuntimeException;
use ZipArchive;
use function count;
use function in_array;
use function is_string;

/**
 * Class Installer
 * @package Grav\Common\GPM
 */
class Installer
{
    /** @const No error */
    public const OK = 0;
    /** @const Target already exists */
    public const EXISTS = 1;
    /** @const Target is a symbolic link */
    public const IS_LINK = 2;
    /** @const Target doesn't exist */
    public const NOT_FOUND = 4;
    /** @const Target is not a directory */
    public const NOT_DIRECTORY = 8;
    /** @const Target is not a Grav instance */
    public const NOT_GRAV_ROOT = 16;
    /** @const Error while trying to open the ZIP package */
    public const ZIP_OPEN_ERROR = 32;
    /** @const Error while trying to extract the ZIP package */
    public const ZIP_EXTRACT_ERROR = 64;
    /** @const Invalid source file */
    public const INVALID_SOURCE = 128;

    /** @var string Destination folder on which validation checks are applied */
    protected static $target;

    /** @var int|string Error code or string */
    protected static $error = 0;

    /** @var int Zip Error Code */
    protected static $error_zip = 0;

    /** @var string Post install message */
    protected static $message = '';

    /** @var array Default options for the install */
    protected static $options = [
        'overwrite'       => true,
        'ignore_symlinks' => true,
        'sophisticated'   => false,
        'theme'           => false,
        'install_path'    => '',
        'ignores'         => [],
        'exclude_checks'  => [self::EXISTS, self::NOT_FOUND, self::IS_LINK]
    ];

    /**
     * Installs a given package to a given destination.
     *
     * @param  string $zip the local path to ZIP package
     * @param  string $destination The local path to the Grav Instance
     * @param  array $options Options to use for installing. ie, ['install_path' => 'user/themes/antimatter']
     * @param  string|null $extracted The local path to the extacted ZIP package
     * @param  bool $keepExtracted True if you want to keep the original files
     * @return bool True if everything went fine, False otherwise.
     */
    public static function install($zip, $destination, $options = [], $extracted = null, $keepExtracted = false)
    {
        $destination = rtrim($destination, DS);
        $options = array_merge(self::$options, $options);
        $install_path = rtrim($destination . DS . ltrim($options['install_path'], DS), DS);

        if (!self::isGravInstance($destination) || !self::isValidDestination(
            $install_path,
            $options['exclude_checks']
        )
        ) {
            return false;
        }

        if ((self::lastErrorCode() === self::IS_LINK && $options['ignore_symlinks']) ||
            (self::lastErrorCode() === self::EXISTS && !$options['overwrite'])
        ) {
            return false;
        }

        // Create a tmp location
        $tmp_dir = Grav::instance()['locator']->findResource('tmp://', true, true);
        $tmp = $tmp_dir . '/Grav-' . uniqid('', false);

        if (!$extracted) {
            $extracted = self::unZip($zip, $tmp);
            if (!$extracted) {
                Folder::delete($tmp);
                return false;
            }
        }

        if (!file_exists($extracted)) {
            self::$error = self::INVALID_SOURCE;
            return false;
        }

        $is_install = true;
        $installer = self::loadInstaller($extracted, $is_install);

        if (isset($options['is_update']) && $options['is_update'] === true) {
            $method = 'preUpdate';
        } else {
            $method = 'preInstall';
        }

        if ($installer && method_exists($installer, $method)) {
            $method_result = $installer::$method();
            if ($method_result !== true) {
                self::$error = 'An error occurred';
                if (is_string($method_result)) {
                    self::$error = $method_result;
                }

                return false;
            }
        }

        if (!$options['sophisticated']) {
            $isTheme = $options['theme'] ?? false;
            // Make sure that themes are always being copied, even if option was not set!
            $isTheme = $isTheme || preg_match('|/themes/[^/]+|ui', $install_path);
            if ($isTheme) {
                self::copyInstall($extracted, $install_path);
            } else {
                self::moveInstall($extracted, $install_path);
            }
        } else {
            self::sophisticatedInstall($extracted, $install_path, $options['ignores'], $keepExtracted);
        }

        Folder::delete($tmp);

        if (isset($options['is_update']) && $options['is_update'] === true) {
            $method = 'postUpdate';
        } else {
            $method = 'postInstall';
        }

        self::$message = '';
        if ($installer && method_exists($installer, $method)) {
            self::$message = $installer::$method();
        }

        self::$error = self::OK;

        return true;
    }

    /**
     * Unzip a file to somewhere
     *
     * @param string $zip_file
     * @param string $destination
     * @return string|false
     */
    public static function unZip($zip_file, $destination)
    {
        $zip = new ZipArchive();
        $archive = $zip->open($zip_file);

        if ($archive === true) {
            Folder::create($destination);

            $unzip = $zip->extractTo($destination);


            if (!$unzip) {
                self::$error = self::ZIP_EXTRACT_ERROR;
                Folder::delete($destination);
                $zip->close();
                return false;
            }

            $package_folder_name = $zip->getNameIndex(0);
            if ($package_folder_name === false) {
                throw new \RuntimeException('Bad package file: ' . basename($zip_file));
            }
            $package_folder_name = preg_replace('#\./$#', '', $package_folder_name);
            $zip->close();

            return $destination . '/' . $package_folder_name;
        }

        self::$error = self::ZIP_EXTRACT_ERROR;
        self::$error_zip = $archive;

        return false;
    }

    /**
     * Instantiates and returns the package installer class
     *
     * @param string $installer_file_folder The folder path that contains install.php
     * @param bool $is_install True if install, false if removal
     * @return string|null
     */
    private static function loadInstaller($installer_file_folder, $is_install)
    {
        $installer_file_folder = rtrim($installer_file_folder, DS);

        $install_file = $installer_file_folder . DS . 'install.php';

        if (!file_exists($install_file)) {
            return null;
        }

        require_once $install_file;

        if ($is_install) {
            $slug = '';
            if (($pos = strpos($installer_file_folder, 'grav-plugin-')) !== false) {
                $slug = substr($installer_file_folder, $pos + strlen('grav-plugin-'));
            } elseif (($pos = strpos($installer_file_folder, 'grav-theme-')) !== false) {
                $slug = substr($installer_file_folder, $pos + strlen('grav-theme-'));
            }
        } else {
            $path_elements = explode('/', $installer_file_folder);
            $slug = end($path_elements);
        }

        if (!$slug) {
            return null;
        }

        $class_name = ucfirst($slug) . 'Install';

        if (class_exists($class_name)) {
            return $class_name;
        }

        $class_name_alphanumeric = preg_replace('/[^a-zA-Z0-9]+/', '', $class_name) ?? $class_name;

        if (class_exists($class_name_alphanumeric)) {
            return $class_name_alphanumeric;
        }

        return null;
    }

    /**
     * @param string            $source_path
     * @param string            $install_path
     * @return bool
     */
    public static function moveInstall($source_path, $install_path)
    {
        if (file_exists($install_path)) {
            Folder::delete($install_path);
        }

        Folder::move($source_path, $install_path);

        return true;
    }

    /**
     * @param string            $source_path
     * @param string            $install_path
     * @return bool
     */
    public static function copyInstall($source_path, $install_path)
    {
        if (empty($source_path)) {
            throw new RuntimeException("Directory $source_path is missing");
        }

        Folder::rcopy($source_path, $install_path);

        return true;
    }

    /**
     * @param string            $source_path
     * @param string            $install_path
     * @param array             $ignores
     * @param bool              $keep_source
     * @return bool
     */
    public static function sophisticatedInstall($source_path, $install_path, $ignores = [], $keep_source = false)
    {
        foreach (new DirectoryIterator($source_path) as $file) {
            if ($file->isLink() || $file->isDot() || in_array($file->getFilename(), $ignores, true)) {
                continue;
            }

            $path = $install_path . DS . $file->getFilename();

            if ($file->isDir()) {
                Folder::delete($path);
                if ($keep_source) {
                    Folder::copy($file->getPathname(), $path);
                } else {
                    Folder::move($file->getPathname(), $path);
                }

                if ($file->getFilename() === 'bin') {
                    $glob = glob($path . DS . '*') ?: [];
                    foreach ($glob as $bin_file) {
                        @chmod($bin_file, 0755);
                    }
                }
            } else {
                @unlink($path);
                @copy($file->getPathname(), $path);
            }
        }

        return true;
    }

    /**
     * Uninstalls one or more given package
     *
     * @param  string $path    The slug of the package(s)
     * @param  array  $options Options to use for uninstalling
     * @return bool True if everything went fine, False otherwise.
     */
    public static function uninstall($path, $options = [])
    {
        $options = array_merge(self::$options, $options);
        if (!self::isValidDestination($path, $options['exclude_checks'])
        ) {
            return false;
        }

        $installer_file_folder = $path;
        $is_install = false;
        $installer = self::loadInstaller($installer_file_folder, $is_install);

        if ($installer && method_exists($installer, 'preUninstall')) {
            $method_result = $installer::preUninstall();
            if ($method_result !== true) {
                self::$error = 'An error occurred';
                if (is_string($method_result)) {
                    self::$error = $method_result;
                }

                return false;
            }
        }

        $result = Folder::delete($path);

        self::$message = '';
        if ($result && $installer && method_exists($installer, 'postUninstall')) {
            self::$message = $installer::postUninstall();
        }

        return $result;
    }

    /**
     * Runs a set of checks on the destination and sets the Error if any
     *
     * @param  string $destination The directory to run validations at
     * @param  array  $exclude     An array of constants to exclude from the validation
     * @return bool True if validation passed. False otherwise
     */
    public static function isValidDestination($destination, $exclude = [])
    {
        self::$error = 0;
        self::$target = $destination;

        if (is_link($destination)) {
            self::$error = self::IS_LINK;
        } elseif (file_exists($destination)) {
            self::$error = self::EXISTS;
        } elseif (!file_exists($destination)) {
            self::$error = self::NOT_FOUND;
        } elseif (!is_dir($destination)) {
            self::$error = self::NOT_DIRECTORY;
        }

        if (count($exclude) && in_array(self::$error, $exclude, true)) {
            return true;
        }

        return !self::$error;
    }

    /**
     * Validates if the given path is a Grav Instance
     *
     * @param  string $target The local path to the Grav Instance
     * @return bool True if is a Grav Instance. False otherwise
     */
    public static function isGravInstance($target)
    {
        self::$error = 0;
        self::$target = $target;

        if (!file_exists($target . DS . 'index.php') ||
            !file_exists($target . DS . 'bin') ||
            !file_exists($target . DS . 'user') ||
            !file_exists($target . DS . 'system' . DS . 'config' . DS . 'system.yaml')
        ) {
            self::$error = self::NOT_GRAV_ROOT;
        }

        return !self::$error;
    }

    /**
     * Returns the last message added by the installer
     *
     * @return string The message
     */
    public static function getMessage()
    {
        return self::$message;
    }

    /**
     * Returns the last error occurred in a string message format
     *
     * @return string The message of the last error
     */
    public static function lastErrorMsg()
    {
        if (is_string(self::$error)) {
            return self::$error;
        }

        switch (self::$error) {
            case 0:
                $msg = 'No Error';
                break;

            case self::EXISTS:
                $msg = 'The target path "' . self::$target . '" already exists';
                break;

            case self::IS_LINK:
                $msg = 'The target path "' . self::$target . '" is a symbolic link';
                break;

            case self::NOT_FOUND:
                $msg = 'The target path "' . self::$target . '" does not appear to exist';
                break;

            case self::NOT_DIRECTORY:
                $msg = 'The target path "' . self::$target . '" does not appear to be a folder';
                break;

            case self::NOT_GRAV_ROOT:
                $msg = 'The target path "' . self::$target . '" does not appear to be a Grav instance';
                break;

            case self::ZIP_OPEN_ERROR:
                $msg = 'Unable to open the package file';
                break;

            case self::ZIP_EXTRACT_ERROR:
                $msg = 'Unable to extract the package. ';
                if (self::$error_zip) {
                    switch (self::$error_zip) {
                        case ZipArchive::ER_EXISTS:
                            $msg .= 'File already exists.';
                            break;

                        case ZipArchive::ER_INCONS:
                            $msg .= 'Zip archive inconsistent.';
                            break;

                        case ZipArchive::ER_MEMORY:
                            $msg .= 'Memory allocation failure.';
                            break;

                        case ZipArchive::ER_NOENT:
                            $msg .= 'No such file.';
                            break;

                        case ZipArchive::ER_NOZIP:
                            $msg .= 'Not a zip archive.';
                            break;

                        case ZipArchive::ER_OPEN:
                            $msg .= "Can't open file.";
                            break;

                        case ZipArchive::ER_READ:
                            $msg .= 'Read error.';
                            break;

                        case ZipArchive::ER_SEEK:
                            $msg .= 'Seek error.';
                            break;
                    }
                }
                break;

            case self::INVALID_SOURCE:
                $msg = 'Invalid source file';
                break;

            default:
                $msg = 'Unknown Error';
                break;
        }

        return $msg;
    }

    /**
     * Returns the last error code of the occurred error
     *
     * @return int|string The code of the last error
     */
    public static function lastErrorCode()
    {
        return self::$error;
    }

    /**
     * Allows to manually set an error
     *
     * @param int|string $error the Error code
     * @return void
     */
    public static function setError($error)
    {
        self::$error = $error;
    }
}
