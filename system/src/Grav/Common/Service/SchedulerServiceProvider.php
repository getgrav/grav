<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Scheduler\Scheduler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class SchedulerServiceProvider
 * @package Grav\Common\Service
 */
class SchedulerServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['scheduler'] = function () {
            return new Scheduler();
        };
    }
}
