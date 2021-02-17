<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Exception;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\File\Interfaces\FileInterface;
use Grav\Framework\Filesystem\Filesystem;
use RuntimeException;

/**
 * Class AbstractFile
 * @package Grav\Framework\File
 */
class AbstractFile implements FileInterface
{
    use Serializable;

    /** @var Filesystem */
    private $filesystem;
    /** @var string */
    private $filepath;
    /** @var string|null */
    private $filename;
    /** @var string|null */
    private $path;
    /** @var string|null */
    private $basename;
    /** @var string|null */
    private $extension;
    /** @var resource|null */
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

    /**
     * @return void
     */
    public function __clone()
    {
        $this->handle = null;
        $this->locked = false;
    }

    /**
     * @return array
     */
    final public function __serialize(): array
    {
        return ['filesystem_normalize' => $this->filesystem->getNormalization()] + $this->doSerialize();
    }

    /**
     * @param array $data
     * @return void
     */
    final public function __unserialize(array $data): void
    {
        $this->filesystem = Filesystem::getInstance($data['filesystem_normalize'] ?? null);

        $this->doUnserialize($data);
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getFilePath()
     */
    public function getFilePath(): string
    {
        return $this->filepath;
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getPath()
     */
    public function getPath(): string
    {
        if (null === $this->path) {
            $this->setPathInfo();
        }

        return $this->path ?? '';
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getFilename()
     */
    public function getFilename(): string
    {
        if (null === $this->filename) {
            $this->setPathInfo();
        }

        return $this->filename ?? '';
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getBasename()
     */
    public function getBasename(): string
    {
        if (null === $this->basename) {
            $this->setPathInfo();
        }

        return $this->basename ?? '';
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getExtension()
     */
    public function getExtension(bool $withDot = false): string
    {
        if (null === $this->extension) {
            $this->setPathInfo();
        }

        return ($withDot ? '.' : '') . $this->extension;
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::exists()
     */
    public function exists(): bool
    {
        return is_file($this->filepath);
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getCreationTime()
     */
    public function getCreationTime(): int
    {
        return is_file($this->filepath) ? (int)filectime($this->filepath) : time();
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::getModificationTime()
     */
    public function getModificationTime(): int
    {
        return is_file($this->filepath) ? (int)filemtime($this->filepath) : time();
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::lock()
     */
    public function lock(bool $block = true): bool
    {
        if (!$this->handle) {
            if (!$this->mkdir($this->getPath())) {
                throw new RuntimeException('Creating directory failed for ' . $this->filepath);
            }
            $this->handle = @fopen($this->filepath, 'cb+') ?: null;
            if (!$this->handle) {
                $error = error_get_last();

                throw new RuntimeException("Opening file for writing failed on error {$error['message']}");
            }
        }

        $lock = $block ? LOCK_EX : LOCK_EX | LOCK_NB;

        // Some filesystems do not support file locks, only fail if another process holds the lock.
        $this->locked = flock($this->handle, $lock, $wouldblock) || !$wouldblock;

        return $this->locked;
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::unlock()
     */
    public function unlock(): bool
    {
        if (!$this->handle) {
            return false;
        }

        if ($this->locked) {
            flock($this->handle, LOCK_UN | LOCK_NB);
            $this->locked = false;
        }

        fclose($this->handle);
        $this->handle = null;

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::isLocked()
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::isReadable()
     */
    public function isReadable(): bool
    {
        return is_readable($this->filepath) && is_file($this->filepath);
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::isWritable()
     */
    public function isWritable(): bool
    {
        if (!file_exists($this->filepath)) {
            return $this->isWritablePath($this->getPath());
        }

        return is_writable($this->filepath) && is_file($this->filepath);
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::load()
     */
    public function load()
    {
        return file_get_contents($this->filepath);
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::save()
     */
    public function save($data): void
    {
        $filepath = $this->filepath;
        $dir = $this->getPath();

        if (!$this->mkdir($dir)) {
            throw new RuntimeException('Creating directory failed for ' . $filepath);
        }

        try {
            if ($this->handle) {
                $tmp = true;
                // As we are using non-truncating locking, make sure that the file is empty before writing.
                if (@ftruncate($this->handle, 0) === false || @fwrite($this->handle, $data) === false) {
                    // Writing file failed, throw an error.
                    $tmp = false;
                }
            } else {
                // Support for symlinks.
                $realpath = is_link($filepath) ? realpath($filepath) : $filepath;
                if ($realpath === false) {
                    throw new RuntimeException('Failed to save file ' . $filepath);
                }

                // Create file with a temporary name and rename it to make the save action atomic.
                $tmp = $this->tempname($realpath);
                if (@file_put_contents($tmp, $data) === false) {
                    $tmp = false;
                } elseif (@rename($tmp, $realpath) === false) {
                    @unlink($tmp);
                    $tmp = false;
                }
            }
        } catch (Exception $e) {
            $tmp = false;
        }

        if ($tmp === false) {
            throw new RuntimeException('Failed to save file ' . $filepath);
        }

        // Touch the directory as well, thus marking it modified.
        @touch($dir);
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::rename()
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
     * {@inheritdoc}
     * @see FileInterface::delete()
     */
    public function delete(): bool
    {
        return @unlink($this->filepath);
    }

    /**
     * @param  string  $dir
     * @return bool
     * @throws RuntimeException
     * @internal
     */
    protected function mkdir(string $dir): bool
    {
        // Silence error for open_basedir; should fail in mkdir instead.
        if (@is_dir($dir)) {
            return true;
        }

        $success = @mkdir($dir, 0777, true);

        if (!$success) {
            // Take yet another look, make sure that the folder doesn't exist.
            clearstatcache(true, $dir);
            if (!@is_dir($dir)) {
                return false;
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
     * @return void
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
        /** @var array $pathInfo */
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

    /**
     * @param string $filename
     * @param int $length
     * @return string
     */
    protected function tempname(string $filename, int $length = 5)
    {
        do {
            $test = $filename . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
        } while (file_exists($test));

        return $test;
    }
}
