<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Backup\Backups;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class BackupsServiceProvider
 * @package Grav\Common\Service
 */
class BackupsServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['backups'] = function () {
            $backups = new Backups();
            $backups->setup();

            return $backups;
        };
    }
}
