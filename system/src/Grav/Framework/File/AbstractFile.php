<?php
/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

class AbstractFile
{
    /** @var string */
    private $filepath;

    /** @var string */
    private $filename;

    /** @var string */
    private $path;

    /** @var string */
    private $basename;

    /** @var string */
    private $extension;

    /** @var resource */
    private $handle;

    /** @var bool */
    private $locked = false;

    public function __construct($filepath)
    {
        $this->setFilepath($filepath);
    }

    /**
     * Unlock file when the object gets destroyed.
     */
    public function __destruct()
    {
        if ($this->isLocked()) {
            $this->unlock();
        }
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Get full path to the file.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filepath;
    }

    /**
     * Get path to the file.
     *
     * @return string
     */
    public function getPath()
    {
        if (null === $this->path) {
            $this->setPathInfo();
        }

        return $this->path;
    }

    /**
     * Get filename.
     *
     * @return string
     */
    public function getFilename()
    {
        if (null === $this->filename) {
            $this->setPathInfo();
        }

        return $this->filename;
    }

    /**
     * Return name of the file without extension.
     *
     * @return string
     */
    public function getBasename()
    {
        if (null === $this->basename) {
            $this->setPathInfo();
        }

        return $this->basename;
    }

    /**
     * Return file extension.
     *
     * @param $withDot
     * @return string
     */
    public function getExtension($withDot = false)
    {
        if (null === $this->extension) {
            $this->setPathInfo();
        }

        return ($withDot ? '.' : '') . $this->extension;
    }

    /**
     * Check if file exits.
     *
     * @return bool
     */
    public function exists()
    {
        return is_file($this->filepath);
    }

    /**
     * Return file modification time.
     *
     * @return int|bool Timestamp or false if file doesn't exist.
     */
    public function getCreationTime()
    {
        return is_file($this->filepath) ? filectime($this->filepath) : false;
    }

    /**
     * Return file modification time.
     *
     * @return int|bool Timestamp or false if file doesn't exist.
     */
    public function getModificationTime()
    {
        return is_file($this->filepath) ? filemtime($this->filepath) : false;
    }

    /**
     * Lock file for writing. You need to manually unlock().
     *
     * @param bool $block  For non-blocking lock, set the parameter to false.
     * @return bool
     * @throws \RuntimeException
     */
    public function lock($block = true)
    {
        if (!$this->handle) {
            if (!$this->mkdir($this->getPath())) {
                throw new \RuntimeException('Creating directory failed for ' . $this->filepath);
            }
            $this->handle = @fopen($this->filepath, 'cb+');
            if (!$this->handle) {
                $error = error_get_last();

                throw new \RuntimeException("Opening file for writing failed on error {$error['message']}");
            }
        }
        $lock = $block ? LOCK_EX : LOCK_EX | LOCK_NB;
        return $this->locked = $this->handle ? flock($this->handle, $lock) : false;
    }

    /**
     * Unlock file.
     *
     * @return bool
     */
    public function unlock()
    {
        if (!$this->handle) {
            return false;
        }
        if ($this->locked) {
            flock($this->handle, LOCK_UN);
            $this->locked = null;
        }
        fclose($this->handle);
        $this->handle = null;

        return true;
    }

    /**
     * Returns true if file has been locked for writing.
     *
     * @return bool True = locked, false = not locked.
     */
    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * Check if file can be written.
     *
     * @return bool
     */
    public function isWritable()
    {
        return is_writable($this->filepath) || $this->isWritableDir($this->getPath());
    }

    /**
     * (Re)Load a file and return RAW file contents.
     *
     * @return string
     */
    public function load()
    {
        return file_get_contents($this->filepath);
    }

    /**
     * Save file.
     *
     * @param  mixed $data
     * @throws \RuntimeException
     */
    public function save($data)
    {
        $lock = false;
        if (!$this->locked) {
            // Obtain blocking lock or fail.
            if (!$this->lock()) {
                throw new \RuntimeException('Obtaining write lock failed on file: ' . $this->filepath);
            }
            $lock = true;
        }

        // As we are using non-truncating locking, make sure that the file is empty before writing.
        if (@ftruncate($this->handle, 0) === false || @fwrite($this->handle, $data) === false) {
            $this->unlock();
            throw new \RuntimeException('Saving file failed: ' . $this->filepath);
        }

        if ($lock) {
            $this->unlock();
        }

        // Touch the directory as well, thus marking it modified.
        @touch($this->getPath());
    }

    /**
     * Rename file in the filesystem if it exists.
     *
     * @param string $path
     * @return bool
     */
    public function rename($path)
    {
        if ($this->exists() && !@rename($this->filepath, $path)) {
            return false;
        }

        $this->setFilepath($path);

        return true;
    }

    /**
     * Delete file from filesystem.
     *
     * @return bool
     */
    public function delete()
    {
        return @unlink($this->filepath);
    }

    /**
     * @param  string  $dir
     * @return bool
     * @throws \RuntimeException
     * @internal
     */
    protected function mkdir($dir)
    {
        // Silence error for open_basedir; should fail in mkdir instead.
        if (!@is_dir($dir)) {
            $success = @mkdir($dir, 0777, true);

            if (!$success) {
                $error = error_get_last();

                throw new \RuntimeException("Creating directory '{$dir}' failed on error {$error['message']}");
            }
        }

        return true;
    }

    /**
     * @param  string  $dir
     * @return bool
     * @internal
     */
    protected function isWritableDir($dir)
    {
        if ($dir && !file_exists($dir)) {
            return $this->isWritableDir(dirname($dir));
        }

        return $dir && is_dir($dir) && is_writable($dir);
    }

    protected function setFilepath($filepath)
    {
        $this->filepath = $filepath;
        $this->filename = null;
        $this->basename = null;
        $this->path = null;
        $this->extension = null;
    }

    protected function setPathInfo()
    {
        $pathInfo = static::pathinfo($this->filepath);
        $this->filename = $pathInfo['filename'];
        $this->basename = $pathInfo['basename'];
        $this->path = $pathInfo['dirname'];
        $this->extension = $pathInfo['extension'];
    }

    /**
     * Multi-byte-safe pathinfo replacement.
     * Replacement for pathinfo(), but stream, multibyte and cross-platform safe.
     *
     * @see    http://www.php.net/manual/en/function.pathinfo.php
     *
     * @param string     $path    A filename or path, does not need to exist as a file
     * @param int|string $options Either a PATHINFO_* constant,
     *                            or a string name to return only the specified piece
     *
     * @return string|array
     */
    public static function pathinfo($path, $options = null)
    {
        $ret = ['scheme' => '', 'dirname' => '', 'basename' => '', 'extension' => '', 'filename' => ''];
        $pathinfo = [];
        if (preg_match('#^((.*?)://)?(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$#um', $path, $pathinfo)) {
            if (array_key_exists(1, $pathinfo)) {
                $ret['scheme'] = $pathinfo[2];
                $ret['dirname'] = $pathinfo[1];
            }
            if (array_key_exists(3, $pathinfo)) {
                $ret['dirname'] .= $pathinfo[3];
            }
            if (array_key_exists(4, $pathinfo)) {
                $ret['basename'] = $pathinfo[4];
            }
            if (array_key_exists(7, $pathinfo)) {
                $ret['extension'] = $pathinfo[7];
            }
            if (array_key_exists(5, $pathinfo)) {
                $ret['filename'] = $pathinfo[5];
            }
        }
        switch ($options) {
            case PATHINFO_DIRNAME:
            case 'dirname':
                return $ret['dirname'];
            case PATHINFO_BASENAME:
            case 'basename':
                return $ret['basename'];
            case PATHINFO_EXTENSION:
            case 'extension':
                return $ret['extension'];
            case PATHINFO_FILENAME:
            case 'filename':
                return $ret['filename'];
            default:
                return $ret;
        }
    }
}
