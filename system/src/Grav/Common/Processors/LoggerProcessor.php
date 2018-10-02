<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Config\Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;

class LoggerProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = '_logger';
    public $title = 'Logger';

    public function process()
    {
        $grav = $this->container;
        /** @var Config $config */
        $config = $grav['config'];
        $log = $grav['log'];
        $handler = $config->get('system.log.handler', 'file');


        switch ($handler) {
            case 'syslog':
                $log->popHandler();

                $facility = $config->get('system.log.syslog.facility', 'local6');
                $logHandler = new SyslogHandler('grav', $facility);
                $formatter = new LineFormatter("%channel%.%level_name%: %message% %extra%");
                $logHandler->setFormatter($formatter);

                $log->pushHandler($logHandler);
                break;
        }

        return $log;
    }
}
