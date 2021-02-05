<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Processors\Events\RequestHandlerEvent;
use Grav\Common\Uri;
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

        $header = $request->getHeaderLine('Content-Type');
        $type = trim(strstr($header, ';', true) ?: $header);
        if ($type === 'application/json') {
            $request = $request->withParsedBody(json_decode($request->getBody()->getContents(), true));
        }

        $uri = $request->getUri();
        $ext = mb_strtolower(pathinfo($uri->getPath(), PATHINFO_EXTENSION));

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
