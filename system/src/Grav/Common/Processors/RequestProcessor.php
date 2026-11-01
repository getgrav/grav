<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Helpers\JsonBody;
use Grav\Common\Processors\Events\RequestHandlerEvent;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RequestProcessor
 * @package Grav\Common\Processors
 */
class RequestProcessor extends ProcessorBase
{
    /** @var string */
    public $id = 'request';
    /** @var string */
    public $title = 'Request';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();

        // InitializeProcessor has already decoded a JSON body; this only does it for a
        // request that did not pass through there. A JSON scalar ("a string", 12345,
        // true) is not a body a handler can read, so it leaves no parsed body.
        $request = JsonBody::parseRequest($request);
        $response = JsonBody::rejection($request);
        if ($response) {
            $this->stopTimer();

            return $response;
        }

        $uri = $request->getUri();
        $ext = mb_strtolower(Utils::pathinfo($uri->getPath(), PATHINFO_EXTENSION));

        $request = $request
            ->withAttribute('grav', $this->container)
            ->withAttribute('time', $_SERVER['REQUEST_TIME_FLOAT'] ?? GRAV_REQUEST_TIME)
            ->withAttribute('route', Uri::getCurrentRoute()->withExtension($ext))
            ->withAttribute('referrer', $this->container['uri']->referrer());

        $event = new RequestHandlerEvent(['request' => $request, 'handler' => $handler]);
        /** @var RequestHandlerEvent $event */
        $event = $this->container->fireEvent('onRequestHandlerInit', $event);
        $response = $event->getResponse();
        $this->stopTimer();

        if ($response) {
            return $response;
        }

        return $handler->handle($request);
    }
}
