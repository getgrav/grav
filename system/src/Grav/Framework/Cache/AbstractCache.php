<?php
/**
 * @package    Grav\Framework\Cache
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Cache;

/**
 * Cache trait for PSR-16 compatible "Simple Cache" implementation
 * @package Grav\Framework\Cache
 */
abstract class AbstractCache implements CacheInterface
{
    use CacheTrait;

    /**
     * @param string $namespace
     * @param null|int|\DateInterval $defaultLifetime
     */
    public function __construct($namespace = '', $defaultLifetime = null)
    {
        $this->init($namespace, $defaultLifetime);
    }
}
