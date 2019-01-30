<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class LoggerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['log'] = function ($c) {
            $log = new Logger('grav');

            /** @var UniformResourceLocator $locator */
            $locator = $c['locator'];

            $log_file = $locator->findResource('log://grav.log', true, true);
            $log->pushHandler(new StreamHandler($log_file, Logger::DEBUG));

            return $log;
        };
    }
}
