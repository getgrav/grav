<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Scheduler\ModernScheduler;
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
        $container['scheduler'] = function ($c) {
            $config = $c['config'];
            
            // Use ModernScheduler if modern features are enabled
            $modernEnabled = $config->get('scheduler.modern.enabled', false);
            
            if ($modernEnabled) {
                return new ModernScheduler();
            }
            
            return new Scheduler();
        };
    }
}
