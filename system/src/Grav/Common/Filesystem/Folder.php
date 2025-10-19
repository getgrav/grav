<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use DirectoryIterator;
use Exception;
use FilesystemIterator;
use Grav\Common\Grav;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function count;
use function dirname;
use function is_callable;

/**
 * Class Folder
 * @package Grav\Common\Filesystem
 */
abstract class Folder
{
    /**
     * Recursively find the last modified time under given path.
     *
     * @param array $paths
     * @return int
     */
    public static function lastModifiedFolder(array $paths): int
    {
        $last_modified = 0;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $flags = RecursiveDirectoryIterator::SKIP_DOTS;

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                return 0;
            }
            if ($locator->isStream($path)) {
                $directory = $locator->getRecursiveIterator($path, $flags);
            } else {
                $directory = new RecursiveDirectoryIterator($path, $flags);
            }
            $filter  = new RecursiveFolderFilterIterator($directory);
            $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iterator as $dir) {
                $dir_modified = $dir->getMTime();
                if ($dir_modified > $last_modified) {
                    $last_modified = $dir_modified;
                }
            }
        }

        return $last_modified;
    }

    /**
     * Recursively find the last modified time under given path by file.
     *
     * @param array  $paths
     * @param string  $extensions   which files to search for specifically
     * @return int
     */
    public static function lastModifiedFile(array $paths, $extensions = 'md|yaml'): int
    {
        $last_modified = 0;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $flags = RecursiveDirectoryIterator::SKIP_DOTS;

        foreach($paths as $path) {
            if (!file_exists($path)) {
                return 0;
            }
            if ($locator->isStream($path)) {
                $directory = $locator->getRecursiveIterator($path, $flags);
            } else {
                $directory = new RecursiveDirectoryIterator($path, $flags);
            }
            $recursive = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
            $iterator = new RegexIterator($recursive, '/^.+\.'.$extensions.'$/i');

            /** @var RecursiveDirectoryIterator $file */
            foreach ($iterator as $file) {
                try {
                    $file_modified = $file->getMTime();
                    if ($file_modified > $last_modified) {
                        $last_modified = $file_modified;
                    }
                } catch (Exception $e) {
                    Grav::instance()['log']->error('Could not process file: ' . $e->getMessage());
                }
            }
        }

        return $last_modified;
    }

    /**
     * Recursively md5 hash all files in a path
     *
     * @param array $paths
     * @return string
     */
    public static function hashAllFiles(array $paths): string
    {
        $files = [];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $flags = RecursiveDirectoryIterator::SKIP_DOTS;

                /** @var UniformResourceLocator $locator */
                $locator = Grav::instance()['locator'];
                if ($locator->isStream($path)) {
                    $directory = $locator->getRecursiveIterator($path, $flags);
                } else {
                    $directory = new RecursiveDirectoryIterator($path, $flags);
                }

                $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

                foreach ($iterator as $file) {
                    $files[] = $file->getPathname() . '?'. $file->getMTime();
                }
            }
        }

        return md5(serialize($files));
    }

    /**
     * Get relative path between target and base path. If path isn't relative, return full path.
     *
     * @param string $path
     * @param string $base
     * @return string
     */
    public static function getRelativePath($path, $base = GRAV_ROOT)
    {
        if ($base) {
            $base = preg_replace('![\\\/]+!', '/', $base);
            $path = preg_replace('![\\\/]+!', '/', $path);
            if (strpos($path, $base) === 0) {
                $path = ltrim(substr($path, strlen($base)), '/');
            }
        }

        return $path;
    }

    /**
     * Get relative path between target and base path. If path isn't relative, return full path.
     *
     * @param  string  $path
     * @param  string  $base
     * @return string
     */
    public static function getRelativePathDotDot($path, $base)
    {
        // Normalize paths.
        $base = preg_replace('![\\\/]+!', '/', $base);
        $path = preg_replace('![\\\/]+!', '/', $path);

        if ($path === $base) {
            return '';
        }

        $baseParts = explode('/', ltrim($base, '/'));
        $pathParts = explode('/', ltrim($path, '/'));

        array_pop($baseParts);
        $lastPart = array_pop($pathParts);
        foreach ($baseParts as $i => $directory) {
            if (isset($pathParts[$i]) && $pathParts[$i] === $directory) {
                unset($baseParts[$i], $pathParts[$i]);
            } else {
                break;
            }
        }
        $pathParts[] = $lastPart;
        $path = str_repeat('../', count($baseParts)) . implode('/', $pathParts);

        return '' === $path
        || strpos($path, '/') === 0
        || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }

    /**
     * Shift first directory out of the path.
     *
     * @param string $path
     * @return string|null
     */
    public static function shift(&$path)
    {
        $parts = explode('/', trim($path, '/'), 2);
        $result = array_shift($parts);
        $path = array_shift($parts);

        return $result ?: null;
    }

    /**
     * Return recursive list of all files and directories under given path.
     *
     * @param  string            $path
     * @param  array             $params
     * @return array
     * @throws RuntimeException
     */
    public static function all($path, array $params = [])
    {
        if (!$path) {
            throw new RuntimeException("Path doesn't exist.");
        }
        if (!file_exists($path)) {
            return [];
        }

        $compare = isset($params['compare']) ? 'get' . $params['compare'] : null;
        $pattern = $params['pattern'] ?? null;
        $filters = $params['filters'] ?? null;
        $recursive = $params['recursive'] ?? true;
        $levels = $params['levels'] ?? -1;
        $key = isset($params['key']) ? 'get' . $params['key'] : null;
        $value = 'get' . ($params['value'] ?? ($recursive ? 'SubPathname' : 'Filename'));
        $folders = $params['folders'] ?? true;
        $files = $params['files'] ?? true;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if ($recursive) {
            $flags = RecursiveDirectoryIterator::SKIP_DOTS + FilesystemIterator::UNIX_PATHS
                + FilesystemIterator::CURRENT_AS_SELF + FilesystemIterator::FOLLOW_SYMLINKS;
            if ($locator->isStream($path)) {
                $directory = $locator->getRecursiveIterator($path, $flags);
            } else {
                $directory = new RecursiveDirectoryIterator($path, $flags);
            }
            $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
            $iterator->setMaxDepth(max($levels, -1));
        } else {
            if ($locator->isStream($path)) {
                $iterator = $locator->getIterator($path);
            } else {
                $iterator = new FilesystemIterator($path);
            }
        }

        $results = [];

        /** @var RecursiveDirectoryIterator $file */
        foreach ($iterator as $file) {
            // Ignore hidden files.
            if (strpos($file->getFilename(), '.') === 0 && $file->isFile()) {
                continue;
            }
            if (!$folders && $file->isDir()) {
                continue;
            }
            if (!$files && $file->isFile()) {
                continue;
            }
            if ($compare && $pattern && !preg_match($pattern, $file->{$compare}())) {
                continue;
            }
            $fileKey = $key ? $file->{$key}() : null;
            $filePath = $file->{$value}();
            if ($filters) {
                if (isset($filters['key'])) {
                    $pre = !empty($filters['pre-key']) ? $filters['pre-key'] : '';
                    $fileKey = $pre . preg_replace($filters['key'], '', $fileKey);
                }
                if (isset($filters['value'])) {
                    $filter = $filters['value'];
                    if (is_callable($filter)) {
                        $filePath = $filter($file);
                    } else {
                        $filePath = preg_replace($filter, '', $filePath);
                    }
                }
            }

            if ($fileKey !== null) {
                $results[$fileKey] = $filePath;
            } else {
                $results[] = $filePath;
            }
        }

        return $results;
    }

    /**
     * Recursively copy directory in filesystem.
     *
     * @param  string $source
     * @param  string $target
     * @param  string|null $ignore  Ignore files matching pattern (regular expression).
     * @return void
     * @throws RuntimeException
     */
    public static function copy($source, $target, $ignore = null)
    {
        $source = rtrim($source, '\\/');
        $target = rtrim($target, '\\/');

        if (!is_dir($source)) {
            throw new RuntimeException('Cannot copy non-existing folder.');
        }

        // Make sure that path to the target exists before copying.
        self::create($target);

        $success = true;

        // Go through all sub-directories and copy everything.
        $files = self::all($source);
        foreach ($files as $file) {
            if ($ignore && preg_match($ignore, $file)) {
                continue;
            }
            $src = $source .'/'. $file;
            $dst = $target .'/'. $file;

            if (is_dir($src)) {
                // Create current directory (if it doesn't exist).
                if (!is_dir($dst)) {
                    $success &= @mkdir($dst, 0777, true);
                }
            } else {
                // Or copy current file.
                $success &= @copy($src, $dst);
            }
        }

        if (!$success) {
            $error = error_get_last();
            throw new RuntimeException($error['message'] ?? 'Unknown error');
        }

        // Make sure that the change will be detected when caching.
        @touch(dirname($target));
    }

    /**
     * Move directory in filesystem.
     *
     * @param  string $source
     * @param  string $target
     * @return void
     * @throws RuntimeException
     */
    public static function move($source, $target)
    {
        if (!file_exists($source) || !is_dir($source)) {
            // Rename fails if source folder does not exist.
            throw new RuntimeException('Cannot move non-existing folder.');
        }

        // Don't do anything if the source is the same as the new target
        if ($source === $target) {
            return;
        }

        if (strpos($target, $source . '/') === 0) {
            throw new RuntimeException('Cannot move folder to itself');
        }

        if (file_exists($target)) {
            // Rename fails if target folder exists.
            throw new RuntimeException('Cannot move files to existing folder/file.');
        }

        // Make sure that path to the target exists before moving.
        self::create(dirname($target));

        // Silence warnings (chmod failed etc).
        @rename($source, $target);

        // Rename function can fail while still succeeding, so let's check if the folder exists.
        if (is_dir($source)) {
            // Rename doesn't support moving folders across filesystems. Use copy instead.
            self::copy($source, $target);
            self::delete($source);
        }

        // Make sure that the change will be detected when caching.
        @touch(dirname($source));
        @touch(dirname($target));
        @touch($target);
    }

    /**
     * Recursively delete directory from filesystem.
     *
     * @param  string $target
     * @param  bool   $include_target
     * @return bool
     * @throws RuntimeException
     */
    public static function delete($target, $include_target = true)
    {
        if (!is_dir($target)) {
            return false;
        }

        $success = self::doDelete($target, $include_target);

        if (!$success) {
            $error = error_get_last();

            throw new RuntimeException($error['message'] ?? 'Unknown error');
        }

        // Make sure that the change will be detected when caching.
        if ($include_target) {
            @touch(dirname($target));
        } else {
            @touch($target);
        }

        return $success;
    }

    /**
     * @param  string  $folder
     * @return void
     * @throws RuntimeException
     */
    public static function mkdir($folder)
    {
        self::create($folder);
    }

    /**
     * @param  string  $folder
     * @return void
     * @throws RuntimeException
     */
    public static function create($folder)
    {
        // Silence error for open_basedir; should fail in mkdir instead.
        if (@is_dir($folder)) {
            return;
        }

        $success = @mkdir($folder, 0777, true);

        if (!$success) {
            // Take yet another look, make sure that the folder doesn't exist.
            clearstatcache(true, $folder);
            if (!@is_dir($folder)) {
                throw new RuntimeException(sprintf('Unable to create directory: %s', $folder));
            }
        }
    }

    /**
     * Recursive copy of one directory to another
     *
     * @param string $src
     * @param string $dest
     * @return bool
     * @throws RuntimeException
     */
    public static function rcopy($src, $dest, $preservePermissions = false)
    {

        // If the src is not a directory do a simple file copy
        if (!is_dir($src)) {
            copy($src, $dest);
            if ($preservePermissions) {
                $perm = @fileperms($src);
                if ($perm !== false) {
                    @chmod($dest, $perm & 0777);
                }
                $mtime = @filemtime($src);
                if ($mtime !== false) {
                    @touch($dest, $mtime);
                }
            }
            return true;
        }

        // If the destination directory does not exist create it
        if (!is_dir($dest)) {
            static::create($dest);
        }

        if ($preservePermissions) {
            $perm = @fileperms($src);
            if ($perm !== false) {
                @chmod($dest, $perm & 0777);
            }
        }

        // Open the source directory to read in files
        $i = new DirectoryIterator($src);
        foreach ($i as $f) {
            if ($f->isFile()) {
                $target = "{$dest}/" . $f->getFilename();
                copy($f->getRealPath(), $target);
                if ($preservePermissions) {
                    $perm = @fileperms($f->getRealPath());
                    if ($perm !== false) {
                        @chmod($target, $perm & 0777);
                    }
                    $mtime = @filemtime($f->getRealPath());
                    if ($mtime !== false) {
                        @touch($target, $mtime);
                    }
                }
            } else {
                if (!$f->isDot() && $f->isDir()) {
                    static::rcopy($f->getRealPath(), "{$dest}/{$f}", $preservePermissions);
                }
            }
        }
        return true;
    }

    /**
     * Does a directory contain children
     *
     * @param string $directory
     * @return int|false
     */
    public static function countChildren($directory)
    {
        if (!is_dir($directory)) {
            return false;
        }
        $directories = glob($directory . '/*', GLOB_ONLYDIR);

        return $directories ? count($directories) : false;
    }

    /**
     * @param  string $folder
     * @param  bool   $include_target
     * @return bool
     * @internal
     */
    protected static function doDelete($folder, $include_target = true)
    {
        // Special case for symbolic links.
        if ($include_target && is_link($folder)) {
            return @unlink($folder);
        }

        // Go through all items in filesystem and recursively remove everything.
        $files = scandir($folder, SCANDIR_SORT_NONE);
        $files = $files ? array_diff($files, ['.', '..']) : [];
        foreach ($files as $file) {
            $path = "{$folder}/{$file}";
            is_dir($path) ? self::doDelete($path) : @unlink($path);
        }

        return $include_target ? @rmdir($folder) : true;
    }
}
