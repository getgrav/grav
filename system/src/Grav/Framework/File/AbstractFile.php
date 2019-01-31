<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Grav\Framework\File\Interfaces\FileInterface;
use Grav\Framework\Filesystem\Filesystem;

class AbstractFile implements FileInterface
{
    /** @var Filesystem */
    private $filesystem;

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

    /**
     * @param string $filepath
     * @param Filesystem|null $filesystem
     */
    public function __construct(string $filepath, Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? Filesystem::getInstance();
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

    public function __clone()
    {
        $this->handle = null;
        $this->locked = false;
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->doSerialize());
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $this->doUnserialize(unserialize($serialized, ['allowed_classes' => false]));
    }

    /**
     * Get full path to the file.
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filepath;
    }

    /**
     * Get path to the file.
     *
     * @return string
     */
    public function getPath(): string
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
    public function getFilename(): string
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
    public function getBasename(): string
    {
        if (null === $this->basename) {
            $this->setPathInfo();
        }

        return $this->basename;
    }

    /**
     * Return file extension.
     *
     * @param bool $withDot
     * @return string
     */
    public function getExtension(bool $withDot = false): string
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
    public function exists(): bool
    {
        return is_file($this->filepath);
    }

    /**
     * Return file modification time.
     *
     * @return int Unix timestamp. If file does not exist, method returns current time.
     */
    public function getCreationTime(): int
    {
        return is_file($this->filepath) ? filectime($this->filepath) : time();
    }

    /**
     * Return file modification time.
     *
     * @return int Unix timestamp. If file does not exist, method returns current time.
     */
    public function getModificationTime(): int
    {
        return is_file($this->filepath) ? filemtime($this->filepath) : time();
    }

    /**
     * Lock file for writing. You need to manually unlock().
     *
     * @param bool $block  For non-blocking lock, set the parameter to false.
     * @return bool
     * @throws \RuntimeException
     */
    public function lock(bool $block = true): bool
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
    public function unlock(): bool
    {
        if (!$this->handle) {
            return false;
        }
        if ($this->locked) {
            flock($this->handle, LOCK_UN);
            $this->locked = false;
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
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * Check if file exists and can be read.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return is_readable($this->filepath) && is_file($this->filepath);
    }

    /**
     * Check if file can be written.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        if (!file_exists($this->filepath)) {
            return $this->isWritablePath($this->getPath());
        }

        return is_writable($this->filepath) && is_file($this->filepath);
    }

    /**
     * (Re)Load a file and return file contents.
     *
     * @return string|array|false
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
    public function save($data): void
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
    public function rename(string $path): bool
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
    public function delete(): bool
    {
        return @unlink($this->filepath);
    }

    /**
     * @param  string  $dir
     * @return bool
     * @throws \RuntimeException
     * @internal
     */
    protected function mkdir(string $dir): bool
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
     * @return array
     */
    protected function doSerialize(): array
    {
        return [
            'filepath' => $this->filepath
        ];
    }

    /**
     * @param array $serialized
     */
    protected function doUnserialize(array $serialized): void
    {
        $this->setFilepath($serialized['filepath']);
    }

    /**
     * @param string $filepath
     */
    protected function setFilepath(string $filepath): void
    {
        $this->filepath = $filepath;
        $this->filename = null;
        $this->basename = null;
        $this->path = null;
        $this->extension = null;
    }

    protected function setPathInfo(): void
    {
        $pathInfo = $this->filesystem->pathinfo($this->filepath);

        $this->filename = $pathInfo['filename'] ?? null;
        $this->basename = $pathInfo['basename'] ?? null;
        $this->path = $pathInfo['dirname'] ?? null;
        $this->extension = $pathInfo['extension'] ?? null;
    }

    /**
     * @param  string  $dir
     * @return bool
     * @internal
     */
    protected function isWritablePath(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }

        if (!file_exists($dir)) {
            // Recursively look up in the directory tree.
            return $this->isWritablePath($this->filesystem->parent($dir));
        }

        return is_dir($dir) && is_writable($dir);
    }
}
