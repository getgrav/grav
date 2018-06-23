<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class LoggerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['log'] = function ($c) {
            /** @var Config $config */
            $config = $c['config'];
            $handler = $config->get('system.log.handler', 'file');

            $log = new Logger('grav');

            /** @var UniformResourceLocator $locator */
            $locator = $c['locator'];

            switch ($handler) {
                case 'syslog':
                    $facility = $config->get('system.log.syslog.facility', 'local6');
                    $logHandler = new SyslogHandler('grav', $facility);
                    $formatter = new LineFormatter("%channel%.%level_name%: %message% %extra%");
                    $logHandler->setFormatter($formatter);
                    break;
                case 'file':
                default:
                    $log_file = $locator->findResource('log://grav.log', true, true);
                    $logHandler = new StreamHandler($log_file, Logger::DEBUG);
            }

            $log->pushHandler($logHandler);

            return $log;
        };
    }
}
