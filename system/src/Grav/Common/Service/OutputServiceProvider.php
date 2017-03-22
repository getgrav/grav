<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class OutputServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['output'] = function ($c) {
            return $c['twig']->processSite($c['page']->templateFormat());
        };
    }
}
