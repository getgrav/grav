<?php
/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache;

use Grav\Framework\Cache\Exception\InvalidArgumentException;

/**
 * Cache trait for PSR-16 compatible "Simple Cache" implementation
 * @package Grav\Framework\Cache
 */
trait CacheTrait
{
    /** @var string */
    private $namespace = '';

    /** @var int|null */
    private $defaultLifetime = null;

    /** @var \stdClass */
    private $miss;

    /** @var bool */
    private $validation = true;

    /**
     * Always call from constructor.
     *
     * @param string $namespace
     * @param null|int|\DateInterval $defaultLifetime
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function init($namespace = '', $defaultLifetime = null)
    {
        $this->namespace = (string) $namespace;
        $this->defaultLifetime = $this->convertTtl($defaultLifetime);
        $this->miss = new \stdClass;
    }

    /**
     * @param $validation
     */
    public function setValidation($validation)
    {
        $this->validation = (bool) $validation;
    }

    /**
     * @return string
     */
    protected function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return int|null
     */
    protected function getDefaultLifetime()
    {
        return $this->defaultLifetime;
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get($key, $default = null)
    {
        $this->validateKey($key);

        $value = $this->doGet($key, $this->miss);

        return $value !== $this->miss ? $value : $default;
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set($key, $value, $ttl = null)
    {
        $this->validateKey($key);

        $ttl = $this->convertTtl($ttl);

        // If a negative or zero TTL is provided, the item MUST be deleted from the cache.
        return null !== $ttl && $ttl <= 0 ? $this->doDelete($key) : $this->doSet($key, $value, $ttl);
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete($key)
    {
        $this->validateKey($key);

        return $this->doDelete($key);
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        return $this->doClear();
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache keys must be array or Traversable, "%s" given',
                    is_object($keys) ? get_class($keys) : gettype($keys)
                )
            );
        }

        if (empty($keys)) {
            return [];
        }

        $this->validateKeys($keys);
        $keys = array_unique($keys);
        $keys = array_combine($keys, $keys);

        $list = $this->doGetMultiple($keys, $this->miss);

        // Make sure that values are returned in the same order as the keys were given.
        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $list) || $list[$key] === $this->miss) {
                $values[$key] = $default;
            } else {
                $values[$key] = $list[$key];
            }
        }

        return $values;
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function setMultiple($values, $ttl = null)
    {
        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values, true);
        } elseif (!is_array($values)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache values must be array or Traversable, "%s" given',
                    is_object($values) ? get_class($values) : gettype($values)
                )
            );
        }

        $keys = array_keys($values);

        if (empty($keys)) {
            return true;
        }

        $this->validateKeys($keys);

        $ttl = $this->convertTtl($ttl);

        // If a negative or zero TTL is provided, the item MUST be deleted from the cache.
        return null !== $ttl && $ttl <= 0 ? $this->doDeleteMultiple($keys) : $this->doSetMultiple($values, $ttl);
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function deleteMultiple($keys)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache keys must be array or Traversable, "%s" given',
                    is_object($keys) ? get_class($keys) : gettype($keys)
                )
            );
        }

        if (empty($keys)) {
            return true;
        }

        $this->validateKeys($keys);

        return $this->doDeleteMultiple($keys);
    }

    /**
     * @inheritdoc
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function has($key)
    {
        $this->validateKey($key);

        return $this->doHas($key);
    }

    abstract public function doGet($key, $miss);
    abstract public function doSet($key, $value, $ttl);
    abstract public function doDelete($key);
    abstract public function doClear();

    /**
     * @param array $keys
     * @param mixed $miss
     * @return array
     */
    public function doGetMultiple($keys, $miss)
    {
        $results = [];

        foreach ($keys as $key) {
            $value = $this->doGet($key, $miss);
            if ($value !== $miss) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * @param array $values
     * @param int $ttl
     * @return bool
     */
    public function doSetMultiple($values, $ttl)
    {
        $success = true;

        foreach ($values as $key => $value) {
            $success = $this->doSet($key, $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * @param array $keys
     * @return bool
     */
    public function doDeleteMultiple($keys)
    {
        $success = true;

        foreach ($keys as $key) {
            $success = $this->doDelete($key) && $success;
        }

        return $success;
    }

    abstract public function doHas($key);

    /**
     * @param string $key
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache key must be string, "%s" given',
                    is_object($key) ? get_class($key) : gettype($key)
                )
            );
        }
        if (!isset($key[0])) {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }
        if (strlen($key) > 64) {
            throw new InvalidArgumentException(
                sprintf('Cache key length must be less than 65 characters, key had %s characters', strlen($key))
            );
        }
        if (strpbrk($key, '{}()/\@:') !== false) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" contains reserved characters {}()/\@:', $key)
            );
        }
    }

    /**
     * @param array $keys
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function validateKeys($keys)
    {
        if (!$this->validation) {
            return;
        }

        foreach ($keys as $key) {
            $this->validateKey($key);
        }
    }

    /**
     * @param null|int|\DateInterval    $ttl
     * @return int|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function convertTtl($ttl)
    {
        if ($ttl === null) {
            return $this->getDefaultLifetime();
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            $ttl = (int) \DateTime::createFromFormat('U', 0)->add($ttl)->format('U');
        }

        throw new InvalidArgumentException(
            sprintf(
                'Expiration date must be an integer, a DateInterval or null, "%s" given',
                is_object($ttl) ? get_class($ttl) : gettype($ttl)
            )
        );
    }
}
