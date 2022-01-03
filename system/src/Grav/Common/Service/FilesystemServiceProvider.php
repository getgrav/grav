<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Framework\Filesystem\Filesystem;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class FilesystemServiceProvider
 * @package Grav\Common\Service
 */
class FilesystemServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['filesystem'] = function () {
            return Filesystem::getInstance();
        };
    }
}
