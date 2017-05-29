<?php
/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use Grav\Framework\Cache\AbstractCache;

/**
 * Cache class for PSR-16 compatible "Simple Cache" implementation using in memory backend.
 * Memory backend does not use namespace or default ttl as the cache is unique to each cache object and request.
 *
 * @package Grav\Framework\Cache
 */
class MemoryCache extends AbstractCache
{
    /**
     * @var array
     */
    protected $cache = [];

    /**
     * Memory Cache constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function doGet($key)
    {
        if (!array_key_exists($key, $this->cache)) {
            // Cache misses.
            $this->cache[$key] = $this->miss();
        }

        return $this->cache[$key];
    }

    public function doSet($key, $value, $ttl)
    {
        $this->cache[$key] = $value;

        return true;
    }

    public function doDelete($key)
    {
        $this->cache[$key] = $this->miss();

        return true;
    }

    public function doClear()
    {
        $this->cache = [];

        return true;
    }

    public function doHas($key)
    {
        return array_key_exists($key, $this->cache) && $this->isHit($this->cache[$key]);
    }
}
