<?php

/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache\Adapter;

use Grav\Framework\Cache\AbstractCache;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Cache\Exception\InvalidArgumentException;

/**
 * Cache class for PSR-16 compatible "Simple Cache" implementation using chained cache adapters.
 *
 * @package Grav\Framework\Cache
 */
class ChainCache extends AbstractCache
{
    /**
     * @var CacheInterface[]
     */
    protected $caches;

    /**
     * @var int
     */
    protected $count;

    /**
     * Chain Cache constructor.
     * @param array $caches
     * @param null|int|\DateInterval $defaultLifetime
     * @throws \Psr\SimpleCache\InvalidArgumentException|InvalidArgumentException
     */
    public function __construct(array $caches, $defaultLifetime = null)
    {
        parent::__construct('', $defaultLifetime);

        if (!$caches) {
            throw new InvalidArgumentException('At least one cache must be specified');
        }

        foreach ($caches as $cache) {
            if (!$cache instanceof CacheInterface) {
                throw new InvalidArgumentException(
                    sprintf(
                        "The class '%s' does not implement the '%s' interface",
                        \get_class($cache),
                        CacheInterface::class
                    )
                );
            }
        }

        $this->caches = array_values($caches);
        $this->count = \count($caches);
    }

    /**
     * @inheritdoc
     */
    public function doGet($key, $miss)
    {
        foreach ($this->caches as $i => $cache) {
            $value = $cache->doGet($key, $miss);
            if ($value !== $miss) {
                while (--$i >= 0) {
                    // Update all the previous caches with missing value.
                    $this->caches[$i]->doSet($key, $value, $this->getDefaultLifetime());
                }

                return $value;
            }
        }

        return $miss;
    }

    /**
     * @inheritdoc
     */
    public function doSet($key, $value, $ttl)
    {
        $success = true;
        $i = $this->count;

        while ($i--) {
            $success = $this->caches[$i]->doSet($key, $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function doDelete($key)
    {
        $success = true;
        $i = $this->count;

        while ($i--) {
            $success = $this->caches[$i]->doDelete($key) && $success;
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function doClear()
    {
        $success = true;
        $i = $this->count;

        while ($i--) {
            $success = $this->caches[$i]->doClear() && $success;
        }
        return $success;
    }

    /**
     * @inheritdoc
     */
    public function doGetMultiple($keys, $miss)
    {
        $list = [];
        foreach ($this->caches as $i => $cache) {
            $list[$i] = $cache->doGetMultiple($keys, $miss);

            $keys = array_diff_key($keys, $list[$i]);

            if (!$keys) {
                break;
            }
        }

        $values = [];
        // Update all the previous caches with missing values.
        foreach (array_reverse($list) as $i => $items) {
            $values += $items;
            if ($i && $values) {
                $this->caches[$i-1]->doSetMultiple($values, $this->getDefaultLifetime());
            }
        }

        return $values;
    }

    /**
     * @inheritdoc
     */
    public function doSetMultiple($values, $ttl)
    {
        $success = true;
        $i = $this->count;

        while ($i--) {
            $success = $this->caches[$i]->doSetMultiple($values, $ttl) && $success;
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function doDeleteMultiple($keys)
    {
        $success = true;
        $i = $this->count;

        while ($i--) {
            $success = $this->caches[$i]->doDeleteMultiple($keys) && $success;
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function doHas($key)
    {
        foreach ($this->caches as $cache) {
            if ($cache->doHas($key)) {
                return true;
            }
        }

        return false;
    }
}
