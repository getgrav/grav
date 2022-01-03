<?php

/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use ErrorException;
use FilesystemIterator;
use Grav\Framework\Cache\AbstractCache;
use Grav\Framework\Cache\Exception\CacheException;
use Grav\Framework\Cache\Exception\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use function strlen;

/**
 * Cache class for PSR-16 compatible "Simple Cache" implementation using file backend.
 *
 * Defaults to 1 year TTL. Does not support unlimited TTL.
 *
 * @package Grav\Framework\Cache
 */
class FileCache extends AbstractCache
{
    /** @var string */
    private $directory;
    /** @var string|null */
    private $tmp;

    /**
     * FileCache constructor.
     * @param string $namespace
     * @param int|null $defaultLifetime
     * @param string|null $folder
     * @throws \Psr\SimpleCache\InvalidArgumentException|InvalidArgumentException
     */
    public function __construct($namespace = '', $defaultLifetime = null, $folder = null)
    {
        try {
            parent::__construct($namespace, $defaultLifetime ?: 31557600); // = 1 year

            $this->initFileCache($namespace, $folder ?? '');
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function doGet($key, $miss)
    {
        $now = time();
        $file = $this->getFile($key);

        if (!file_exists($file) || !$h = @fopen($file, 'rb')) {
            return $miss;
        }

        if ($now >= (int) $expiresAt = fgets($h)) {
            fclose($h);
            @unlink($file);
        } else {
            $i = rawurldecode(rtrim((string)fgets($h)));
            $value = stream_get_contents($h) ?: '';
            fclose($h);

            if ($i === $key) {
                return unserialize($value, ['allowed_classes' => true]);
            }
        }

        return $miss;
    }

    /**
     * @inheritdoc
     * @throws CacheException
     */
    public function doSet($key, $value, $ttl)
    {
        $expiresAt = time() + (int)$ttl;

        $result = $this->write(
            $this->getFile($key, true),
            $expiresAt . "\n" . rawurlencode($key) . "\n" . serialize($value),
            $expiresAt
        );

        if (!$result && !is_writable($this->directory)) {
            throw new CacheException(sprintf('Cache directory is not writable (%s)', $this->directory));
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function doDelete($key)
    {
        $file = $this->getFile($key);

        $result = false;
        if (file_exists($file)) {
            $result = @unlink($file);
            $result &= !file_exists($file);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function doClear()
    {
        $result = true;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $result = ($file->isDir() || @unlink($file) || !file_exists($file)) && $result;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function doHas($key)
    {
        $file = $this->getFile($key);

        return file_exists($file) && (@filemtime($file) > time() || $this->doGet($key, null));
    }

    /**
     * @param string $key
     * @param bool $mkdir
     * @return string
     */
    protected function getFile($key, $mkdir = false)
    {
        $hash = str_replace('/', '-', base64_encode(hash('sha256', static::class . $key, true)));
        $dir = $this->directory . $hash[0] . DIRECTORY_SEPARATOR . $hash[1] . DIRECTORY_SEPARATOR;

        if ($mkdir) {
            $this->mkdir($dir);
        }

        return $dir . substr($hash, 2, 20);
    }

    /**
     * @param string $namespace
     * @param string $directory
     * @return void
     * @throws InvalidArgumentException
     */
    protected function initFileCache($namespace, $directory)
    {
        if ($directory === '') {
            $directory = sys_get_temp_dir() . '/grav-cache';
        } else {
            $directory = realpath($directory) ?: $directory;
        }

        if (isset($namespace[0])) {
            if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
                throw new InvalidArgumentException(sprintf('Namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
            }
            $directory .= DIRECTORY_SEPARATOR . $namespace;
        }

        $this->mkdir($directory);

        $directory .= DIRECTORY_SEPARATOR;
        // On Windows the whole path is limited to 258 chars
        if ('\\' === DIRECTORY_SEPARATOR && strlen($directory) > 234) {
            throw new InvalidArgumentException(sprintf('Cache folder is too long (%s)', $directory));
        }
        $this->directory = $directory;
    }

    /**
     * @param string $file
     * @param string $data
     * @param int|null $expiresAt
     * @return bool
     */
    private function write($file, $data, $expiresAt = null)
    {
        set_error_handler(__CLASS__.'::throwError');

        try {
            if ($this->tmp === null) {
                $this->tmp = $this->directory . uniqid('', true);
            }

            file_put_contents($this->tmp, $data);

            if ($expiresAt !== null) {
                touch($this->tmp, $expiresAt);
            }

            return rename($this->tmp, $file);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param  string  $dir
     * @return void
     * @throws RuntimeException
     */
    private function mkdir($dir)
    {
        // Silence error for open_basedir; should fail in mkdir instead.
        if (@is_dir($dir)) {
            return;
        }

        $success = @mkdir($dir, 0777, true);

        if (!$success) {
            // Take yet another look, make sure that the folder doesn't exist.
            clearstatcache(true, $dir);
            if (!@is_dir($dir)) {
                throw new RuntimeException(sprintf('Unable to create directory: %s', $dir));
            }
        }
    }

    /**
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     * @internal
     * @throws ErrorException
     */
    public static function throwError($type, $message, $file, $line)
    {
        throw new ErrorException($message, 0, $type, $file, $line);
    }

    /**
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function __destruct()
    {
        if ($this->tmp !== null && file_exists($this->tmp)) {
            unlink($this->tmp);
        }
    }
}
