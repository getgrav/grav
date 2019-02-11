<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors\Events;

use Grav\Framework\RequestHandler\RequestHandler;
use Grav\Framework\Route\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use RocketTheme\Toolbox\Event\Event;

class RequestHandlerEvent extends Event
{
    /**
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->offsetGet('request');
    }

    /**
     * @return Route
     */
    public function getRoute(): Route
    {
        return $this->getRequest()->getAttribute('route');
    }

    /**
     * @return RequestHandler
     */
    public function getHandler(): RequestHandler
    {
        return $this->offsetGet('handler');
    }

    /**
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->offsetGet('response');
    }

    /**
     * @param ResponseInterface $response
     * @return $this
     */
    public function setResponse(ResponseInterface $response): self
    {
        $this->offsetSet('response', $response);
        $this->stopPropagation();

        return $this;
    }

    /**
     * @param string $name
     * @param MiddlewareInterface $middleware
     * @return RequestHandlerEvent
     */
    public function addMiddleware(string $name, MiddlewareInterface $middleware): self
    {
        /** @var RequestHandler $handler */
        $handler = $this['handler'];
        $handler->addMiddleware($name, $middleware);

        return $this;
    }
}
