<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
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
        $container['task'] = function ($c) {
            /** @var Grav $c */
            return !empty($_POST['task']) ? $_POST['task'] : $c['uri']->param('task');
        };
    }
}
