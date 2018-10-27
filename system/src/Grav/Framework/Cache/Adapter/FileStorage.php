<?php
/**
 * @package Grav\Framework\Cache
 *
 * @license MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use Grav\Framework\Cache\AbstractCache;
use Grav\Framework\Cache\Exception\CacheException;
use Grav\Framework\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Storage class for PSR-16 compatible "Simple Cache" implementation using file 
 * backend. Defaults to 1 year TTL.
 *
 * @package Grav\Framework\Cache
 */
class FileStorage extends AbstractCache
{
    /**
     * Path to Storage-directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Temporary filename.
     *
     * @var string
     */
    protected $tmp;

    /**
     * Initiate Storage
     *
     * @param string $directory       Path to directory.
     * @param string $namespace       Name of something.
     * @param int    $defaultLifetime Duration.
     */
    public function __construct($directory, $namespace = '', $defaultLifetime = null)
    {
        parent::__construct($namespace, $defaultLifetime ?: 31557600);
        $this->directory = $directory;
    }

    /**
     * Fetches a value from storage.
     *
     * @param string $key  The unique key of this item in storage.
     * @param mixed  $miss Value to return if the key does not exist.
     *
     * @return mixed The value of the item from storage, or $miss if non-existant.
     * 
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function doGet($key, $miss = false)
    {
        parent::validateKey($key);
        $now = time();
        $file = $this->directory . DIRECTORY_SEPARATOR . $key;

        if (!file_exists($file) || !$h = @fopen($file, 'rb')) {
            return $miss;
        }

        if ($now >= (int) $expiresAt = fgets($h)) {
            fclose($h);
            @unlink($file);
        } else {
            $i = rawurldecode(rtrim(fgets($h)));
            $value = stream_get_contents($h);
            fclose($h);
            if ($i === $key) {
                return unserialize($value);
            }
        }

        return $miss;
    }

    /**
     * Persists data in storage.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store.
     * @param null|int|\DateInterval $ttl   The Time To Live value of this item.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Component\Filesystem\Exception\IOExceptionInterface
     */
    public function doSet($key, $value, $ttl = 31556926)
    {
        parent::validateKey($key);
        $expiresAt = time() + (int) $ttl;
        $file = $this->directory . DIRECTORY_SEPARATOR . $key;

        if (!is_writable($this->directory)) {
            mkdir($this->directory, 0755, true);
        } else {
            try {
                if ($this->tmp === null) {
                    $this->tmp = $this->directory . DIRECTORY_SEPARATOR . uniqid('', true);
                }

                file_put_contents($this->tmp, $value, LOCK_EX);

                if ($expiresAt !== null) {
                    touch($this->tmp, $expiresAt);
                }

                return rename($this->tmp, $file);
            } catch (\CacheException $e) {
                throw $e;
            } finally {
                restore_error_handler();
            }
        }
    }

    /**
     * Delete an item from storage.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was removed, false otherwise.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function doDelete($key)
    {
        parent::validateKey($key);
        $file = $this->directory . DIRECTORY_SEPARATOR . $key;

        return (!file_exists($file) || @unlink($file) || !file_exists($file));
    }

    /**
     * Wipes clean the entire storage's keys.
     *
     * @return bool True on success, false otherwise.
     */
    public function doClear()
    {
        $target = new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($target);
        foreach ($iterator as $file) {
            $this->doDelete($file->getFilename());
        }
        $result = new \FilesystemIterator($this->directory);
        if (!$result->valid()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determines whether an item is present in storage.
     *
     * @param string $key The unique cache key of the item to check for.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function doHas($key)
    {
        parent::validateKey($key);
        $file = $this->directory . DIRECTORY_SEPARATOR . $key;
        return file_exists($file) && (@filemtime($file) > time() || $this->doGet($key, null));
    }
}
