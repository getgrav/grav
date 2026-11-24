<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Grav;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class TaskServiceProvider
 * @package Grav\Common\Service
 */
class TaskServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['task'] = function (Grav $c) {
            /** @var ServerRequestInterface $request */
            $request = $c['request'];
            $body = $request->getParsedBody();

            $task = $body['task'] ?? $c['uri']->param('task');
            // Only a string names a task: `task[]=x` in a request is ignored, not an error.
            $task = is_string($task) ? htmlspecialchars(strip_tags($task), ENT_QUOTES, 'UTF-8') : null;

            return $task ?: null;
        };

        $container['action'] = function (Grav $c) {
            /** @var ServerRequestInterface $request */
            $request = $c['request'];
            $body = $request->getParsedBody();

            $action = $body['action'] ?? $c['uri']->param('action');
            // Only a string names an action: `action[]=x` in a request is ignored, not an error.
            $action = is_string($action) ? htmlspecialchars(strip_tags($action), ENT_QUOTES, 'UTF-8') : null;

            return $action ?: null;
        };
    }
}
