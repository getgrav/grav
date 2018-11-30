<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
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
    }
}
