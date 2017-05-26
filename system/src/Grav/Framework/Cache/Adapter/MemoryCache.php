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
 * Cache class for PSR-16 compatible "Simple Cache" implementation using memory backend.
 * Memory backend does not use namespace or ttl as the cache is unique to each cache object and request.
 *
 * @package Grav\Framework\Cache
 */
class MemoryCache extends AbstractCache
{
    protected $cache = [];

    /**
     * Doctrine Cache constructor.
     *
     * @param string $namespace
     * @param null|int|\DateInterval $defaultLifetime
     */
    public function __construct($namespace = '', $defaultLifetime = null)
    {
        // Do not use $namespace or $defaultLifetime directly, store them with constructor and fetch with methods.
        parent::__construct($namespace, $defaultLifetime);
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
