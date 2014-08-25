<?php
namespace Grav\Component\Filesystem\StreamWrapper;

use Grav\Component\Filesystem\ResourceLocator;

class Stream implements StreamInterface
{
    /**
     * A generic resource handle.
     *
     * @var Resource
     */
    protected $handle = null;

    /**
     * @var ResourceLocator
     */
    protected static $locator;

    /**
     * @param ResourceLocator $locator
     */
    public static function setLocator(ResourceLocator $locator)
    {
        static::$locator = $locator;
    }

    public function stream_open($uri, $mode, $options, &$opened_url)
    {
        $path = $this->getPath($uri, $mode);

        if (!$path) {
            return false;
        }

        $this->handle = ($options & STREAM_REPORT_ERRORS) ? fopen($path, $mode) : @fopen($path, $mode);

        return (bool) $this->handle;
    }

    public function stream_close()
    {
        return fclose($this->handle);
    }

    public function stream_lock($operation)
    {
        if (in_array($operation, [LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB])) {
            return flock($this->handle, $operation);
        }

        return false;
    }

    public function stream_metadata($uri, $option, $value)
    {
        switch ($option) {
            case STREAM_META_TOUCH:
                list ($time, $atime) = $value;
                return touch($uri, $time, $atime);

            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                return chown($uri, $value);

            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                return chgrp($uri, $value);

            case STREAM_META_ACCESS:
                return chmod($uri, $value);
        }

        return false;
    }

    public function stream_read($count)
    {
        return fread($this->handle, $count);
    }

    public function stream_write($data)
    {
        return fwrite($this->handle, $data);
    }

    public function stream_eof()
    {
        return feof($this->handle);
    }

    public function stream_seek($offset, $whence)
    {
        // fseek returns 0 on success and -1 on a failure.
        return !fseek($this->handle, $offset, $whence);
    }

    public function stream_flush()
    {
        return fflush($this->handle);
    }

    public function stream_tell()
    {
        return ftell($this->handle);
    }

    public function stream_stat()
    {
        return fstat($this->handle);
    }

    public function unlink($uri)
    {
        $path = $this->getPath($uri);

        if (!$path) {
            return false;
        }

        return unlink($path);
    }

    public function rename($fromUri, $toUri)
    {
        $fromPath = $this->getPath($fromUri);
        $toPath = $this->getPath($toUri);

        if (!($fromPath && $toPath)) {
            return false;
        }

        return rename($fromPath, $toPath);
    }

    public function mkdir($uri, $mode, $options)
    {
        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
        $path = $this->getPath($uri, $recursive ? $mode : null);

        if (!$path) {
            return false;
        }

        return ($options & STREAM_REPORT_ERRORS) ? mkdir($path, $mode, $recursive) : @mkdir($path, $mode, $recursive);
    }

    public function rmdir($uri, $options)
    {
        $path = $this->getPath($uri);

        if (!$path) {
            return false;
        }

        return ($options & STREAM_REPORT_ERRORS) ? rmdir($path) : @rmdir($path);
    }

    public function url_stat($uri, $flags)
    {
        $path = $this->getPath($uri);

        if (!$path) {
            return false;
        }

        // Suppress warnings if requested or if the file or directory does not
        // exist. This is consistent with PHP's plain filesystem stream wrapper.
        return ($flags & STREAM_URL_STAT_QUIET || !file_exists($path)) ? @stat($path) : stat($path);
    }

    public function dir_opendir($uri, $options)
    {
        $path = $this->getPath($uri);

        if (!$path) {
            return false;
        }

        $this->handle = opendir($path);

        return (bool) $this->handle;
    }

    public function dir_readdir()
    {
        return readdir($this->handle);
    }

    public function dir_rewinddir()
    {
        rewinddir($this->handle);

        return true;
    }

    public function dir_closedir()
    {
        closedir($this->handle);

        return true;
    }

    protected function getPath($uri, $mode = null)
    {
        $path = $this->findPath($uri);

        if ($mode == null || !$path || file_exists($path)) {
            return $path;
        }

        if ($mode[0] == 'r') {
            return false;
        }

        // We are either opening a file or creating directory.
        list($scheme, $target) = explode('://', $uri, 2);

        $path = $this->findPath($scheme . '://' . dirname($target));

        if (!$path) {
            return false;
        }

        return $path . '/' . basename($uri);
    }

    protected function findPath($uri)
    {
        return static::$locator ? static::$locator->findResource($uri) : false;
    }
}
