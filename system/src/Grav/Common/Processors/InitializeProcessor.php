<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Psr7\Response;
use Grav\Framework\Session\Exceptions\SessionException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InitializeProcessor extends ProcessorBase
{
    public $id = '_init';
    public $title = 'Initialize';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->initializeConfig();
        $this->initializeLogger($config);
        $this->initializeErrors();

        $this->startTimer('_debugger', 'Init Debugger');
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger']->init();
        // Clockwork integration.
        $clockwork = $debugger->getClockwork();
        if ($clockwork) {
            $server = $request->getServerParams();
//            $baseUri = str_replace('\\', '/', dirname(parse_url($server['SCRIPT_NAME'], PHP_URL_PATH)));
//            if ($baseUri === '/') {
//                $baseUri = '';
//            }
            $requestTime = $server['REQUEST_TIME_FLOAT'] ?? GRAV_REQUEST_TIME;

            $request = $request->withAttribute('request_time', $requestTime);

            // Handle clockwork API calls.
            $uri = $request->getUri();
            if (Utils::contains($uri->getPath(), '/__clockwork/')) {
                return $debugger->debuggerRequest($request);
            }

            $this->container['clockwork'] = $clockwork;
        }
        $this->stopTimer('_debugger');

        $this->initialize($config);
        $this->initializeSession($config);

        // Wrap call to next handler so that debugger can profile it.
        /** @var Response $response */
        $response = $debugger->profile(function () use ($handler, $request) {
            return $handler->handle($request);
        });

        // Log both request and response and return the response.
        return $debugger->logRequest($request, $response);
    }

    protected function initializeConfig(): Config
    {
        $this->startTimer('_config', 'Configuration');

        // Initialize Configuration
        $grav = $this->container;
        /** @var Config $config */
        $config = $grav['config'];
        $config->init();
        $grav['plugins']->setup();

        $this->stopTimer('_config');

        return $config;
    }

    protected function initializeLogger(Config $config): void
    {
        $this->startTimer('_logger', 'Logger');

        // Initialize Logging
        $grav = $this->container;

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

        $this->stopTimer('_logger');
    }

    protected function initializeErrors(): void
    {
        $this->startTimer('_errors', 'Error Handlers Reset');

        // Initialize Error Handlers
        $this->container['errors']->resetHandlers();

        $this->stopTimer('_errors');
    }

    protected function initialize(Config $config): void
    {
        $this->startTimer('_init', 'Initialize');

        // Use output buffering to prevent headers from being sent too early.
        ob_start();
        if ($config->get('system.cache.gzip') && !@ob_start('ob_gzhandler')) {
            // Enable zip/deflate with a fallback in case of if browser does not support compressing.
            ob_start();
        }

        // Initialize the timezone.
        $timezone = $config->get('system.timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }

        /** @var Uri $uri */
        $uri = $this->container['uri'];
        $uri->init();

        // Redirect pages with trailing slash if configured to do so.
        $path = $uri->path() ?: '/';
        if ($path !== '/'
            && $config->get('system.pages.redirect_trailing_slash', false)
            && Utils::endsWith($path, '/')) {

            $redirect = (string) $uri::getCurrentRoute()->toString();
            $this->container->redirect($redirect);
        }

        $this->container->setLocale();

        $this->stopTimer('_init');
    }

    protected function initializeSession(Config $config): void
    {
        // FIXME: Initialize session should happen later after plugins have been loaded. This is a workaround to fix session issues in AWS.
        if (isset($this->container['session']) && $config->get('system.session.initialize', true)) {
            $this->startTimer('_session', 'Start Session');

            // TODO: remove in 2.0.
            $this->container['accounts'];

            try {
                $this->container['session']->init();
            } catch (SessionException $e) {
                $this->container['session']->init();
                $message = 'Session corruption detected, restarting session...';
                $this->addMessage($message);
                $this->container['messages']->add($message, 'error');
            }

            $this->stopTimer('_session');
        }
    }
}
