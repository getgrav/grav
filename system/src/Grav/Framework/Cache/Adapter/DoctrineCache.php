<?php
/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use Doctrine\Common\Cache\CacheProvider;
use Grav\Framework\Cache\AbstractCache;

/**
 * Cache class for PSR-16 compatible "Simple Cache" implementation using Doctrine Cache backend.
 * @package Grav\Framework\Cache
 */
class DoctrineCache extends AbstractCache
{
    /**
     * @var CacheProvider
     */
    protected $driver;

    /**
     * Doctrine Cache constructor.
     *
     * @param CacheProvider $doctrineCache
     * @param string $namespace
     * @param null|int|\DateInterval $defaultLifetime
     */
    public function __construct(CacheProvider $doctrineCache, $namespace = '', $defaultLifetime = null)
    {
        // Do not use $namespace or $defaultLifetime directly, store them with constructor and fetch with methods.
        parent::__construct($namespace, $defaultLifetime);

        // Set namespace to Doctrine Cache provider if it was given.
        $namespace = $this->getNamespace();
        $namespace && $doctrineCache->setNamespace($namespace);

        $this->driver = $doctrineCache;
    }

    protected function doGet($key, $default)
    {
        $value = $this->driver->fetch($key);

        // Doctrine cache does not differentiate between no result and cached 'false'. Make sure that we do.
        return $value !== false || $this->driver->contains($key) ? $value : $default;
    }

    protected function doSet($key, $value, $ttl)
    {
        return $this->driver->save($key, $value, (int) $ttl);
    }

    protected function doDelete($key)
    {
        return $this->driver->delete($key);
    }

    protected function doClear()
    {
        return $this->driver->deleteAll();
    }

    protected function doGetMultiple($keys, $default)
    {
        return $this->driver->fetchMultiple($keys);
    }

    protected function doSetMultiple($values, $ttl)
    {
        return $this->driver->saveMultiple($values, (int) $ttl);
    }

    protected function doDeleteMultiple($keys)
    {
        // TODO: Remove when Doctrine Cache has been updated to support the feature.
        if (!method_exists($this->driver, 'deleteMultiple')) {
            $success = true;
            foreach ($keys as $key) {
                $success = $this->delete($key) && $success;
            }

            return $success;
        }

        return $this->driver->deleteMultiple($keys);
    }

    protected function doHas($key)
    {
        return $this->driver->contains($key);
    }
}
