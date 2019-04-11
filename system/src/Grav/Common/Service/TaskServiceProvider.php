<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Grav;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class TaskServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['task'] = function (Grav $c) {
            $task = $_POST['task'] ?? $c['uri']->param('task');
            if (null !== $task) {
                $task = filter_var($task, FILTER_SANITIZE_STRING);
            }

            return $task ?: null;
        };

        $container['action'] = function (Grav $c) {
            $action = $_POST['action'] ?? $c['uri']->param('action');
            if (null !== $action) {
                $action = filter_var($action, FILTER_SANITIZE_STRING);
            }

            return $action ?: null;
        };
    }
}
