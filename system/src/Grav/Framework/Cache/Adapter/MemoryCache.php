<?php

/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use Grav\Framework\Cache\AbstractCache;
use function array_key_exists;

/**
 * Cache class for PSR-16 compatible "Simple Cache" implementation using in memory backend.
 *
 * Memory backend does not use namespace or default ttl as the cache is unique to each cache object and request.
 *
 * @package Grav\Framework\Cache
 */
class MemoryCache extends AbstractCache
{
    /** @var array */
    protected $cache = [];

    /**
     * @param string $key
     * @param mixed $miss
     * @return mixed
     */
    public function doGet($key, $miss)
    {
        if (!array_key_exists($key, $this->cache)) {
            return $miss;
        }

        return $this->cache[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function doSet($key, $value, $ttl)
    {
        $this->cache[$key] = $value;

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function doDelete($key)
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * @return bool
     */
    public function doClear()
    {
        $this->cache = [];

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function doHas($key)
    {
        return array_key_exists($key, $this->cache);
    }
}
