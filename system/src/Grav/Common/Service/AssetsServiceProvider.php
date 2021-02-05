<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Grav\Common\Assets;

/**
 * Class AssetsServiceProvider
 * @package Grav\Common\Service
 */
class AssetsServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['assets'] = function () {
            return new Assets();
        };
    }
}
