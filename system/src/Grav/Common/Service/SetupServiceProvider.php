<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Setup;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class SetupServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['setup'] = function (Container $container) {
            $setup = new Setup($container);
            $setup->init();

            return $setup;
        };
    }
}
