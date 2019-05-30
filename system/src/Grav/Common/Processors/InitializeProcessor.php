<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Clockwork\Clockwork;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Helpers\ServerTiming;
use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Psr7\Response;
use Grav\Framework\Session\Exceptions\SessionException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InitializeProcessor extends ProcessorBase
{
    public $id = '_init';
    public $title = 'Initialize';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer('_config', 'Configuration');
        $config = $this->initializeConfig();
        $this->stopTimer('_config');

        $this->startTimer('_logger', 'Logger');
        $this->initializeLogger($config);
        $this->stopTimer('_logger');

        $this->startTimer('_errors', 'Error Handlers Reset');
        $this->initializeErrors();
        $this->stopTimer('_errors');

        $this->startTimer('_debugger', 'Init Debugger');
        /** @var Debugger $debugger */
        $debugger = $this->container['debugger']->init();
        $clockwork = $debugger->getClockwork();
        if ($clockwork) {
            $server = $request->getServerParams();
            $baseUri =  parse_url(dirname($server['PHP_SELF']), PHP_URL_PATH);
            if ($baseUri === '/') {
                $baseUri = '';
            }
            $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? GRAV_REQUEST_TIME;

            $request = $request
                ->withAttribute('base_uri', $baseUri)
                ->withAttribute('request_time', $requestTime);

            // Handle clockwork API calls.
            $uri = $request->getUri();
            if (mb_strpos($uri->getPath(), $baseUri . '/__clockwork/') === 0) {
                return $this->debuggerRequest($request, $clockwork);
            }

            $this->container['clockwork'] = $clockwork;
        }
        $this->stopTimer('_debugger');

        $this->startTimer('_init', 'Initialize');
        $this->initialize($config);
        $this->stopTimer('_init');

        $this->initializeSession($config);

        $response = $handler->handle($request);

        if ($clockwork) {
            $debugger->finalize();

            return $this->logRequest($request, $response, $clockwork);
        }

        return $response;
    }

    protected function logRequest(ServerRequestInterface $request, ResponseInterface $response, Clockwork $clockwork)
    {
        $clockwork->getTimeline()->finalize($request->getAttribute('request_time'));
        $clockwork->addDataSource(new PsrMessageDataSource($request, $response));

        $clockwork->resolveRequest();
        $clockwork->storeRequest();

        $clockworkRequest = $clockwork->getRequest();

        $response = $response
            ->withHeader('X-Clockwork-Id', $clockworkRequest->id)
            ->withHeader('X-Clockwork-Version', $clockwork::VERSION);

        $basePath = $request->getAttribute('base_uri');
        if ($basePath) {
            $response = $response->withHeader('X-Clockwork-Path', $basePath . '/__clockwork/');
        }

        return $response->withHeader('Server-Timing', ServerTiming::fromRequest($clockworkRequest)->value());
    }

    protected function debuggerRequest(RequestInterface $request, Clockwork $clockwork): Response
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Grav-Internal-SkipShutdown' => 1
        ];

        $path = $request->getUri()->getPath();
        $clockworkDataUri = '#/__clockwork(?:/(?<id>[0-9-]+))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#';
        if (preg_match($clockworkDataUri, $path, $matches) === false) {
            $response = ['message' => 'Bad Input'];

            return new Response(400, $headers, json_encode($response));
        }

        $id = $matches['id'] ?? null;
        $direction = $matches['direction'] ?? null;
        $count = $matches['count'] ?? null;

        $storage = $clockwork->getStorage();

        if ($direction === 'previous') {
            $data = $storage->previous($id, $count);
        } elseif ($direction === 'next') {
            $data = $storage->next($id, $count);
        } elseif ($id === 'latest') {
            $data = $storage->latest();
        } else {
            $data = $storage->find($id);
        }

        if (preg_match('#(?<id>[0-9-]+|latest)/extended#', $path)) {
            $clockwork->extendRequest($data);
        }

        if (!$data) {
            $response = ['message' => 'Not Found'];

            return new Response(404, $headers, json_encode($response));
        }

        $data = is_array($data) ? array_map(function ($item) { return $item->toArray(); }, $data) : $data->toArray();

        return new Response(200, $headers, json_encode($data));
    }

    protected function initializeConfig(): Config
    {
        // Initialize Configuration
        $grav = $this->container;
        /** @var Config $config */
        $config = $grav['config'];
        $config->init();
        $grav['plugins']->setup();

        return $config;
    }

    protected function initializeLogger(Config $config)
    {
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
    }

    protected function initializeErrors()
    {
        // Initialize Error Handlers
        $this->container['errors']->resetHandlers();
    }

    protected function initialize(Config $config)
    {
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
    }

    protected function initializeSession(Config $config)
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
