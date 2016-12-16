<?php
/**
 * @package    Grav.Common.FileSystem
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

abstract class Folder
{
    /**
     * Recursively find the last modified time under given path.
     *
     * @param  string $path
     * @return int
     */
    public static function lastModifiedFolder($path)
    {
        $last_modified = 0;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        if ($locator->isStream($path)) {
            $directory = $locator->getRecursiveIterator($path, $flags);
        } else {
            $directory = new \RecursiveDirectoryIterator($path, $flags);
        }
        $filter  = new RecursiveFolderFilterIterator($directory);
        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::SELF_FIRST);

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($iterator as $dir) {
            $dir_modified = $dir->getMTime();
            if ($dir_modified > $last_modified) {
                $last_modified = $dir_modified;
            }
        }

        return $last_modified;
    }

    /**
     * Recursively find the last modified time under given path by file.
     *
     * @param  string $path
     * @param string  $extensions   which files to search for specifically
     *
     * @return int
     */
    public static function lastModifiedFile($path, $extensions = 'md|yaml')
    {
        $last_modified = 0;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        if ($locator->isStream($path)) {
            $directory = $locator->getRecursiveIterator($path, $flags);
        } else {
            $directory = new \RecursiveDirectoryIterator($path, $flags);
        }
        $recursive = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
        $iterator = new \RegexIterator($recursive, '/^.+\.'.$extensions.'$/i');

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($iterator as $filepath => $file) {
            $file_modified = $file->getMTime();
            if ($file_modified > $last_modified) {
                $last_modified = $file_modified;
            }
        }

        return $last_modified;
    }

    /**
     * Recursively md5 hash all files in a path
     *
     * @param $path
     * @return string
     */
    public static function hashAllFiles($path)
    {
        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        $files = [];

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if ($locator->isStream($path)) {
            $directory = $locator->getRecursiveIterator($path, $flags);
        } else {
            $directory = new \RecursiveDirectoryIterator($path, $flags);
        }

        $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $filepath => $file) {
            $files[] = $file->getPath() . $file->getMTime();
        }

        return md5(serialize($files));
    }

    /**
     * Get relative path between target and base path. If path isn't relative, return full path.
     *
     * @param string       $path
     * @param mixed|string $base
     *
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
        $base = preg_replace('![\\\/]+!', '/', $base);
        $path = preg_replace('![\\\/]+!', '/', $path);

        if ($path === $base) {
            return '';
        }

        $baseParts = explode('/', isset($base[0]) && '/' === $base[0] ? substr($base, 1) : $base);
        $pathParts = explode('/', isset($path[0]) && '/' === $path[0] ? substr($path, 1) : $path);

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
        || '/' === $path[0]
        || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }

    /**
     * Shift first directory out of the path.
     *
     * @param string $path
     * @return string
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
     * @throws \RuntimeException
     */
    public static function all($path, array $params = [])
    {
        if ($path === false) {
            throw new \RuntimeException("Path doesn't exist.");
        }

        $compare = isset($params['compare']) ? 'get' . $params['compare'] : null;
        $pattern = isset($params['pattern']) ? $params['pattern'] : null;
        $filters = isset($params['filters']) ? $params['filters'] : null;
        $recursive = isset($params['recursive']) ? $params['recursive'] : true;
        $levels = isset($params['levels']) ? $params['levels'] : -1;
        $key = isset($params['key']) ? 'get' . $params['key'] : null;
        $value = isset($params['value']) ? 'get' . $params['value'] : ($recursive ? 'getSubPathname' : 'getFilename');
        $folders = isset($params['folders']) ? $params['folders'] : true;
        $files = isset($params['files']) ? $params['files'] : true;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if ($recursive) {
            $flags = \RecursiveDirectoryIterator::SKIP_DOTS + \FilesystemIterator::UNIX_PATHS
                + \FilesystemIterator::CURRENT_AS_SELF + \FilesystemIterator::FOLLOW_SYMLINKS;
            if ($locator->isStream($path)) {
                $directory = $locator->getRecursiveIterator($path, $flags);
            } else {
                $directory = new \RecursiveDirectoryIterator($path, $flags);
            }
            $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
            $iterator->setMaxDepth(max($levels, -1));
        } else {
            if ($locator->isStream($path)) {
                $iterator = $locator->getIterator($path);
            } else {
                $iterator = new \FilesystemIterator($path);
            }
        }

        $results = [];

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($iterator as $file) {
            // Ignore hidden files.
            if ($file->getFilename()[0] == '.') {
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
                        $filePath = call_user_func($filter, $file);
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
     * @param  string $ignore  Ignore files matching pattern (regular expression).
     * @throws \RuntimeException
     */
    public static function copy($source, $target, $ignore = null)
    {
        $source = rtrim($source, '\\/');
        $target = rtrim($target, '\\/');

        if (!is_dir($source)) {
            throw new \RuntimeException('Cannot copy non-existing folder.');
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
            throw new \RuntimeException($error['message']);
        }

        // Make sure that the change will be detected when caching.
        @touch(dirname($target));
    }

    /**
     * Move directory in filesystem.
     *
     * @param  string $source
     * @param  string $target
     * @throws \RuntimeException
     */
    public static function move($source, $target)
    {
        if (!is_dir($source)) {
            throw new \RuntimeException('Cannot move non-existing folder.');
        }

        // Don't do anything if the source is the same as the new target
        if ($source == $target) {
            return;
        }

        // Make sure that path to the target exists before moving.
        self::create(dirname($target));

        // Just rename the directory.
        $success = @rename($source, $target);

        if (!$success) {
            $error = error_get_last();
            throw new \RuntimeException($error['message']);
        }

        // Make sure that the change will be detected when caching.
        @touch(dirname($source));
        @touch(dirname($target));
    }

    /**
     * Recursively delete directory from filesystem.
     *
     * @param  string $target
     * @param  bool   $include_target
     * @return bool
     */
    public static function delete($target, $include_target = true)
    {
        if (!is_dir($target)) {
            return false;
        }

        $success = self::doDelete($target, $include_target);

        if (!$success) {
            $error = error_get_last();
            throw new \RuntimeException($error['message']);
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
     * @throws \RuntimeException
     */
    public static function mkdir($folder)
    {
        self::create($folder);
    }

    /**
     * @param  string  $folder
     * @throws \RuntimeException
     */
    public static function create($folder)
    {
        if (is_dir($folder)) {
            return;
        }

        $success = @mkdir($folder, 0777, true);

        if (!$success) {
            $error = error_get_last();
            throw new \RuntimeException($error['message']);
        }
    }

    /**
     * Recursive copy of one directory to another
     *
     * @param $src
     * @param $dest
     *
     * @return bool
     */
    public static function rcopy($src, $dest)
    {

        // If the src is not a directory do a simple file copy
        if (!is_dir($src)) {
            copy($src, $dest);
            return true;
        }

        // If the destination directory does not exist create it
        if (!is_dir($dest)) {
            Folder::mkdir($dest);
        }

        // Open the source directory to read in files
        $i = new \DirectoryIterator($src);
        /** @var \DirectoryIterator $f */
        foreach ($i as $f) {
            if ($f->isFile()) {
                copy($f->getRealPath(), "$dest/" . $f->getFilename());
            } else {
                if (!$f->isDot() && $f->isDir()) {
                    static::rcopy($f->getRealPath(), "$dest/$f");
                }
            }
        }
        return true;
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
        if (is_link($folder)) {
            return @unlink($folder);
        }

        // Go through all items in filesystem and recursively remove everything.
        $files = array_diff(scandir($folder), array('.', '..'));
        foreach ($files as $file) {
            $path = "{$folder}/{$file}";
            (is_dir($path)) ? self::doDelete($path) : @unlink($path);
        }

        return $include_target ? @rmdir($folder) : true;
    }
}
