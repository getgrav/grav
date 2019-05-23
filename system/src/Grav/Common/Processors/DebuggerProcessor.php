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
use Grav\Common\Debugger;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DebuggerProcessor extends ProcessorBase
{
    public $id = '_debugger';
    public $title = 'Init Debugger';

    protected $clockwork;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();

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
                return $this->retrieveRequest($request, $clockwork);
            }

            $this->container['clockwork'] = $clockwork;
        }

        $this->stopTimer();

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

    protected function retrieveRequest(RequestInterface $request, Clockwork $clockwork): Response
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
}
