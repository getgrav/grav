<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Config\Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoggerProcessor extends ProcessorBase
{
    public $id = '_logger';
    public $title = 'Logger';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();

        $grav = $this->container;

        /** @var Config $config */
        $config = $grav['config'];

        switch ($config->get('system.log.handler', 'file')) {
            case 'syslog':
                $log = $grav['log'];
                $log->popHandler();

                $facility = $config->get('system.log.syslog.facility', 'local6');
                $logHandler = new SyslogHandler('grav', $facility);
                $formatter = new LineFormatter("%channel%.%level_name%: %message% %extra%");
                $logHandler->setFormatter($formatter);

                $log->pushHandler($logHandler);
                break;
        }
        $this->stopTimer();

        return $handler->handle($request);
    }
}
