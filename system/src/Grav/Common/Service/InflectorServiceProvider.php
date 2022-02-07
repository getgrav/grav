<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Inflector;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class InflectorServiceProvider
 * @package Grav\Common\Service
 */
class InflectorServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['inflector'] = function () {
            return new Inflector();
        };
    }
}
