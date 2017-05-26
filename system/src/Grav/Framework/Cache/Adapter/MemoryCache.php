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
 * Memory backend does not use namespace or ttl as the cache is unique to each cache object and request.
 *
 * @package Grav\Framework\Cache
 */
class MemoryCache extends AbstractCache
{
    protected $cache = [];

    /**
     * Memory Cache constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function doGet($key, $default)
    {
        return $this->doHas($key) ? $this->cache[$key] : $default;
    }

    protected function doSet($key, $value, $ttl)
    {
        $this->cache[$key] = $value;

        return true;
    }

    protected function doDelete($key)
    {
        unset($this->cache[$key]);

        return true;
    }

    protected function doClear()
    {
        $this->cache = [];

        return true;
    }

    protected function doHas($key)
    {
        return array_key_exists($key, $this->cache);
    }
}
