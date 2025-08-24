<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Scheduler\JobQueue;
use Grav\Common\Scheduler\JobWorker;
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
            $scheduler = new Scheduler();
            
            // Configure modern features if enabled
            $modernConfig = $config->get('scheduler.modern', []);
            if ($modernConfig['enabled'] ?? false) {
                // Initialize components
                $queuePath = $c['locator']->findResource('user-data://scheduler/queue', true, true);
                $statusPath = $c['locator']->findResource('user-data://scheduler/status.yaml', true, true);
                
                // Set modern configuration on scheduler
                $scheduler->setModernConfig($modernConfig);
                
                // Initialize job queue if enabled
                if ($modernConfig['queue']['enabled'] ?? false) {
                    $jobQueue = new JobQueue($queuePath);
                    $scheduler->setJobQueue($jobQueue);
                }
                
                // Initialize workers if enabled
                if ($modernConfig['workers']['enabled'] ?? false) {
                    $workerCount = $modernConfig['workers']['count'] ?? 2;
                    $workers = [];
                    for ($i = 0; $i < $workerCount; $i++) {
                        $workers[] = new JobWorker("worker-{$i}");
                    }
                    $scheduler->setWorkers($workers);
                }
            }
            
            return $scheduler;
        };
    }
}
