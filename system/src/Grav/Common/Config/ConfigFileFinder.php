<?php
/**
 * @package    Grav.Common.Config
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Filesystem\Folder;

class ConfigFileFinder
{
    protected $base = '';

    /**
     * @param string $base
     * @return $this
     */
    public function setBase($base)
    {
        $this->base = $base ? "{$base}/" : '';

        return $this;
    }

    /**
     * Return all locations for all the files with a timestamp.
     *
     * @param  array  $paths    List of folders to look from.
     * @param  string $pattern  Pattern to match the file. Pattern will also be removed from the key.
     * @param  int    $levels   Maximum number of recursive directories.
     * @return array
     */
    public function locateFiles(array $paths, $pattern = '|\.yaml$|', $levels = -1)
    {
        $list = [];
        foreach ($paths as $folder) {
            $list += $this->detectRecursive($folder, $pattern, $levels);
        }
        return $list;
    }

    /**
     * Return all locations for all the files with a timestamp.
     *
     * @param  array  $paths    List of folders to look from.
     * @param  string $pattern  Pattern to match the file. Pattern will also be removed from the key.
     * @param  int    $levels   Maximum number of recursive directories.
     * @return array
     */
    public function getFiles(array $paths, $pattern = '|\.yaml$|', $levels = -1)
    {
        $list = [];
        foreach ($paths as $folder) {
            $path = trim(Folder::getRelativePath($folder), '/');

            $files = $this->detectRecursive($folder, $pattern, $levels);

            $list += $files[trim($path, '/')];
        }
        return $list;
    }

    /**
     * Return all paths for all the files with a timestamp.
     *
     * @param  array  $paths    List of folders to look from.
     * @param  string $pattern  Pattern to match the file. Pattern will also be removed from the key.
     * @param  int    $levels   Maximum number of recursive directories.
     * @return array
     */
    public function listFiles(array $paths, $pattern = '|\.yaml$|', $levels = -1)
    {
        $list = [];
        foreach ($paths as $folder) {
            $list = array_merge_recursive($list, $this->detectAll($folder, $pattern, $levels));
        }
        return $list;
    }

    /**
     * Find filename from a list of folders.
     *
     * Note: Only finds the last override.
     *
     * @param string $filename
     * @param array $folders
     * @return array
     */
    public function locateFileInFolder($filename, array $folders)
    {
        $list = [];
        foreach ($folders as $folder) {
            $list += $this->detectInFolder($folder, $filename);
        }
        return $list;
    }

    /**
     * Find filename from a list of folders.
     *
     * @param array $folders
     * @param string $filename
     * @return array
     */
    public function locateInFolders(array $folders, $filename = null)
    {
        $list = [];
        foreach ($folders as $folder) {
            $path = trim(Folder::getRelativePath($folder), '/');
            $list[$path] = $this->detectInFolder($folder, $filename);
        }
        return $list;
    }

    /**
     * Return all existing locations for a single file with a timestamp.
     *
     * @param  array  $paths   Filesystem paths to look up from.
     * @param  string $name    Configuration file to be located.
     * @param  string $ext     File extension (optional, defaults to .yaml).
     * @return array
     */
    public function locateFile(array $paths, $name, $ext = '.yaml')
    {
        $filename = preg_replace('|[.\/]+|', '/', $name) . $ext;

        $list = [];
        foreach ($paths as $folder) {
            $path = trim(Folder::getRelativePath($folder), '/');

            if (is_file("{$folder}/{$filename}")) {
                $modified = filemtime("{$folder}/{$filename}");
            } else {
                $modified = 0;
            }
            $basename = $this->base . $name;
            $list[$path] = [$basename => ['file' => "{$path}/{$filename}", 'modified' => $modified]];
        }

        return $list;
    }

    /**
     * Detects all directories with a configuration file and returns them with last modification time.
     *
     * @param  string $folder   Location to look up from.
     * @param  string $pattern  Pattern to match the file. Pattern will also be removed from the key.
     * @param  int    $levels   Maximum number of recursive directories.
     * @return array
     * @internal
     */
    protected function detectRecursive($folder, $pattern, $levels)
    {
        $path = trim(Folder::getRelativePath($folder), '/');

        if (is_dir($folder)) {
            // Find all system and user configuration files.
            $options = [
                'levels'  => $levels,
                'compare' => 'Filename',
                'pattern' => $pattern,
                'filters' => [
                    'pre-key' => $this->base,
                    'key' => $pattern,
                    'value' => function (\RecursiveDirectoryIterator $file) use ($path) {
                        return ['file' => "{$path}/{$file->getSubPathname()}", 'modified' => $file->getMTime()];
                    }
                ],
                'key' => 'SubPathname'
            ];

            $list = Folder::all($folder, $options);

            ksort($list);
        } else {
            $list = [];
        }

        return [$path => $list];
    }

    /**
     * Detects all directories with the lookup file and returns them with last modification time.
     *
     * @param  string $folder Location to look up from.
     * @param  string $lookup Filename to be located (defaults to directory name).
     * @return array
     * @internal
     */
    protected function detectInFolder($folder, $lookup = null)
    {
        $folder = rtrim($folder, '/');
        $path = trim(Folder::getRelativePath($folder), '/');
        $base = $path === $folder ? '' : ($path ? substr($folder, 0, -strlen($path)) : $folder . '/');

        $list = [];

        if (is_dir($folder)) {
            $iterator = new \DirectoryIterator($folder);

            /** @var \DirectoryIterator $directory */
            foreach ($iterator as $directory) {
                if (!$directory->isDir() || $directory->isDot()) {
                    continue;
                }

                $name = $directory->getFilename();
                $find = ($lookup ?: $name) . '.yaml';
                $filename = "{$path}/{$name}/{$find}";

                if (file_exists($base . $filename)) {
                    $basename = $this->base . $name;
                    $list[$basename] = ['file' => $filename, 'modified' => filemtime($base . $filename)];
                }
            }
        }

        return $list;
    }

    /**
     * Detects all plugins with a configuration file and returns them with last modification time.
     *
     * @param  string $folder   Location to look up from.
     * @param  string $pattern  Pattern to match the file. Pattern will also be removed from the key.
     * @param  int    $levels   Maximum number of recursive directories.
     * @return array
     * @internal
     */
    protected function detectAll($folder, $pattern, $levels)
    {
        $path = trim(Folder::getRelativePath($folder), '/');

        if (is_dir($folder)) {
            // Find all system and user configuration files.
            $options = [
                'levels'  => $levels,
                'compare' => 'Filename',
                'pattern' => $pattern,
                'filters' => [
                    'pre-key' => $this->base,
                    'key' => $pattern,
                    'value' => function (\RecursiveDirectoryIterator $file) use ($path) {
                        return ["{$path}/{$file->getSubPathname()}" => $file->getMTime()];
                    }
                ],
                'key' => 'SubPathname'
            ];

            $list = Folder::all($folder, $options);

            ksort($list);
        } else {
            $list = [];
        }

        return $list;
    }
}
