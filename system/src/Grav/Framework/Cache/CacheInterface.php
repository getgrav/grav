<?php

/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache;

use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;

/**
 * PSR-16 compatible "Simple Cache" interface.
 * @package Grav\Framework\Object\Storage
 */
interface CacheInterface extends SimpleCacheInterface
{
    /**
     * @param string $key
     * @param mixed $miss
     * @return mixed
     */
    public function doGet($key, $miss);

    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return mixed
     */
    public function doSet($key, $value, $ttl);

    /**
     * @param string $key
     * @return mixed
     */
    public function doDelete($key);

    /**
     * @return bool
     */
    public function doClear();

    /**
     * @param string[] $keys
     * @param mixed $miss
     * @return mixed
     */
    public function doGetMultiple($keys, $miss);

    /**
     * @param array<string, mixed> $values
     * @param int|null $ttl
     * @return mixed
     */
    public function doSetMultiple($values, $ttl);

    /**
     * @param string[] $keys
     * @return mixed
     */
    public function doDeleteMultiple($keys);

    /**
     * @param string $key
     * @return mixed
     */
    public function doHas($key);
}
