<?php
namespace Grav\Common\Filesystem;

/**
 * Folder helper class.
 *
 * @author RocketTheme
 * @license MIT
 */
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

        $dirItr     = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $filterItr  = new RecursiveFolderFilterIterator($dirItr);
        $itr        = new \RecursiveIteratorIterator($filterItr, \RecursiveIteratorIterator::SELF_FIRST);

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($itr as $dir) {
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
     * @param string  $extensions
     *
     * @return int
     */
    public static function lastModifiedFile($path, $extensions = 'md|yaml')
    {
        $last_modified = 0;

        $dirItr = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS);
        $itrItr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::SELF_FIRST);
        $itr = new \RegexIterator($itrItr, '/^.+\.'.$extensions.'$/i');

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($itr as $filepath => $file) {
            $file_modified = $file->getMTime();
            if ($file_modified > $last_modified) {
                $last_modified = $file_modified;
            }
        }

        return $last_modified;
    }

    /**
     * Get relative path between target and base path. If path isn't relative, return full path.
     *
     * @param  string  $path
     * @param  string  $base
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
    public static function all($path, array $params = array())
    {
        if ($path === false) {
            throw new \RuntimeException("Path to {$path} doesn't exist.");
        }

        $compare = isset($params['compare']) ? 'get' . $params['compare'] : null;
        $pattern = isset($params['pattern']) ? $params['pattern'] : null;
        $filters = isset($params['filters']) ? $params['filters'] : null;
        $recursive = isset($params['recursive']) ? $params['recursive'] : true;
        $key = isset($params['key']) ? 'get' . $params['key'] : null;
        $value = isset($params['value']) ? 'get' . $params['value'] : ($recursive ? 'getSubPathname' : 'getFilename');

        if ($recursive) {
            $directory = new \RecursiveDirectoryIterator($path,
                \RecursiveDirectoryIterator::SKIP_DOTS + \FilesystemIterator::UNIX_PATHS + \FilesystemIterator::CURRENT_AS_SELF);
            $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
        } else {
            $iterator = new \FilesystemIterator($path);
        }

        $results = array();

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($iterator as $file) {
            if ($compare && $pattern && !preg_match($pattern, $file->{$compare}())) {
                continue;
            }
            $fileKey = $key ? $file->{$key}() : null;
            $filePath = $file->{$value}();
            if ($filters) {
                if (isset($filters['key'])) {
                    $fileKey = preg_replace($filters['key'], '', $fileKey);
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
     * @param  string            $source
     * @param  string            $target
     * @throws \RuntimeException
     */
    public static function copy($source, $target)
    {
        $source = rtrim($source, '\\/');
        $target = rtrim($target, '\\/');

        if (!is_dir($source)) {
            throw new \RuntimeException('Cannot copy non-existing folder.');
        }

        // Make sure that path to the target exists before copying.
        self::mkdir($target);

        $success = true;

        // Go through all sub-directories and copy everything.
        $files = self::all($source);
        foreach ($files as $file) {
            $src = $source .'/'. $file;
            $dst = $target .'/'. $file;

            if (is_dir($src)) {
                // Create current directory.
                $success &= @mkdir($dst);
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
     * @param  string            $source
     * @param  string            $target
     * @throws \RuntimeException
     */
    public static function move($source, $target)
    {
        if (!is_dir($source)) {
            throw new \RuntimeException('Cannot move non-existing folder.');
        }

        // Make sure that path to the target exists before moving.
        self::mkdir(dirname($target));

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
     * @throws \RuntimeException
     * @return bool
     */
    public static function delete($target)
    {
        if (!is_dir($target)) {
            throw new \RuntimeException('Cannot delete non-existing folder.');
        }

        $success = self::doDelete($target);

        if (!$success) {
            $error = error_get_last();
            throw new \RuntimeException($error['message']);
        }

        // Make sure that the change will be detected when caching.
        @touch(dirname($target));
        return $success;
    }

    /**
     * @param  string            $folder
     * @throws \RuntimeException
     * @internal
     */
    public static function mkdir($folder)
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
            if (!mkdir($dest)) {
                // If the destination directory could not be created stop processing
                return false;
            }
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
     * @return bool
     * @internal
     */
    protected static function doDelete($folder)
    {
        // Special case for symbolic links.
        if (is_link($folder)) {
            return @unlink($folder);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \DirectoryIterator $fileinfo */
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (false === rmdir($fileinfo->getRealPath())) {
                    return false;
                }
            } else {
                if (false === unlink($fileinfo->getRealPath())) {
                    return false;
                }
            }
        }

        return rmdir($folder);
    }
}
