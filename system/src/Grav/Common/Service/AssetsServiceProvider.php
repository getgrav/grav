<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Grav\Common\Assets;

class AssetsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['assets'] = function () {
            return new Assets();
        };
    }
}
