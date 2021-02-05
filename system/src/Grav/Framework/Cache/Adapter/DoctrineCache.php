<?php

/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use DateInterval;
use Doctrine\Common\Cache\CacheProvider;
use Grav\Framework\Cache\AbstractCache;
use Grav\Framework\Cache\Exception\InvalidArgumentException;

/**
 * Cache class for PSR-16 compatible "Simple Cache" implementation using Doctrine Cache backend.
 * @package Grav\Framework\Cache
 */
class DoctrineCache extends AbstractCache
{
    /** @var CacheProvider */
    protected $driver;

    /**
     * Doctrine Cache constructor.
     *
     * @param CacheProvider $doctrineCache
     * @param string $namespace
     * @param null|int|DateInterval $defaultLifetime
     * @throws \Psr\SimpleCache\InvalidArgumentException|InvalidArgumentException
     */
    public function __construct(CacheProvider $doctrineCache, $namespace = '', $defaultLifetime = null)
    {
        // Do not use $namespace or $defaultLifetime directly, store them with constructor and fetch with methods.
        parent::__construct($namespace, $defaultLifetime);

        // Set namespace to Doctrine Cache provider if it was given.
        $namespace = $this->getNamespace();
        if ($namespace) {
            $doctrineCache->setNamespace($namespace);
        }

        $this->driver = $doctrineCache;
    }

    /**
     * @inheritdoc
     */
    public function doGet($key, $miss)
    {
        $value = $this->driver->fetch($key);

        // Doctrine cache does not differentiate between no result and cached 'false'. Make sure that we do.
        return $value !== false || $this->driver->contains($key) ? $value : $miss;
    }

    /**
     * @inheritdoc
     */
    public function doSet($key, $value, $ttl)
    {
        return $this->driver->save($key, $value, (int) $ttl);
    }

    /**
     * @inheritdoc
     */
    public function doDelete($key)
    {
        return $this->driver->delete($key);
    }

    /**
     * @inheritdoc
     */
    public function doClear()
    {
        return $this->driver->deleteAll();
    }

    /**
     * @inheritdoc
     */
    public function doGetMultiple($keys, $miss)
    {
        return $this->driver->fetchMultiple($keys);
    }

    /**
     * @inheritdoc
     */
    public function doSetMultiple($values, $ttl)
    {
        return $this->driver->saveMultiple($values, (int) $ttl);
    }

    /**
     * @inheritdoc
     */
    public function doDeleteMultiple($keys)
    {
        return $this->driver->deleteMultiple($keys);
    }

    /**
     * @inheritdoc
     */
    public function doHas($key)
    {
        return $this->driver->contains($key);
    }
}
