<?php

/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache;

use DateInterval;
use DateTime;
use Grav\Framework\Cache\Exception\InvalidArgumentException;
use stdClass;
use Traversable;
use function array_key_exists;
use function get_class;
use function gettype;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function strlen;

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
    /** @var stdClass */
    private $miss;
    /** @var bool */
    private $validation = true;

    /**
     * Always call from constructor.
     *
     * @param string $namespace
     * @param null|int|DateInterval $defaultLifetime
     * @return void
     * @throws InvalidArgumentException
     */
    protected function init(string $namespace = '', DateInterval|int|null $defaultLifetime = null): void
    {
        $this->namespace = (string) $namespace;
        $this->defaultLifetime = $this->convertTtl($defaultLifetime);
        $this->miss = new stdClass;
    }

    /**
     * @param bool $validation
     * @return void
     */
    public function setValidation(bool $validation): void
    {
        $this->validation = (bool) $validation;
    }

    /**
     * @return string
     */
    protected function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return int|null
     */
    protected function getDefaultLifetime(): ?int
    {
        return $this->defaultLifetime;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null
     * @throws InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        $value = $this->doGet($key, $this->miss);

        return $value !== $this->miss ? $value : $default;
    }

    /**
     * @param string $key
     * @param null|int|DateInterval $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->validateKey($key);

        $ttl = $this->convertTtl($ttl);

        // If a negative or zero TTL is provided, the item MUST be deleted from the cache.
        return null !== $ttl && $ttl <= 0 ? $this->doDelete($key) : $this->doSet($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);

        return $this->doDelete($key);
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        return $this->doClear();
    }

    /**
     * @param iterable $keys
     * @param mixed|null $default
     * @return iterable
     * @throws InvalidArgumentException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            $isObject = is_object($keys);
            throw new InvalidArgumentException(
                sprintf(
                    'Cache keys must be array or Traversable, "%s" given',
                     $isObject ? $keys::class : gettype($keys)
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
     * @param iterable $values
     * @param null|int|DateInterval $ttl
     * @return bool
     * @throws InvalidArgumentException
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        if ($values instanceof Traversable) {
            $values = iterator_to_array($values, true);
        } elseif (!is_array($values)) {
            $isObject = is_object($values);
            throw new InvalidArgumentException(
                sprintf(
                    'Cache values must be array or Traversable, "%s" given',
                    $isObject ? $values::class : gettype($values)
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
     * @param iterable $keys
     * @return bool
     * @throws InvalidArgumentException
     */
    public function deleteMultiple(iterable $keys): bool
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            $isObject = is_object($keys);
            throw new InvalidArgumentException(
                sprintf(
                    'Cache keys must be array or Traversable, "%s" given',
                    $isObject ? $keys::class : gettype($keys)
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
     * @param string $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);

        return $this->doHas($key);
    }

    /**
     * @param array $keys
     * @return array
     */
    public function doGetMultiple($keys, mixed $miss)
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
     * @param int|null $ttl
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

    /**
     * @param string|mixed $key
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateKey(mixed $key): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache key must be string, "%s" given',
                    get_debug_type($key)
                )
            );
        }
        if (!isset($key[0])) {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }
        if (strlen($key) > 64) {
            throw new InvalidArgumentException(
                sprintf('Cache key length must be less than 65 characters, key had %d characters', strlen($key))
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
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateKeys(iterable $keys): void
    {
        if (!$this->validation) {
            return;
        }

        foreach ($keys as $key) {
            $this->validateKey($key);
        }
    }

    /**
     * @param null|int|DateInterval    $ttl
     * @return int|null
     * @throws InvalidArgumentException
     */
    protected function convertTtl(DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return $this->getDefaultLifetime();
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof DateInterval) {
            $date = DateTime::createFromFormat('U', '0');
            $ttl = $date ? (int)$date->add($ttl)->format('U') : 0;

            return $ttl;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Expiration date must be an integer, a DateInterval or null, "%s" given',
                get_debug_type($ttl)
            )
        );
    }
}
