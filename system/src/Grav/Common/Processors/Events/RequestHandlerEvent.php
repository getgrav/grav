<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors\Events;

use Grav\Framework\RequestHandler\RequestHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use RocketTheme\Toolbox\Event\Event;

class RequestHandlerEvent extends Event
{
    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->offsetGet('request');
    }

    /**
     * @return RequestHandler
     */
    public function getHandler(): RequestHandler
    {
        return $this->offsetGet('handler');
    }

    /**
     * @param string $name
     * @param MiddlewareInterface $middleware
     */
    public function addMiddleware(string $name, MiddlewareInterface $middleware): void
    {
        /** @var RequestHandler $handler */
        $handler = $this['handler'];
        $handler->addMiddleware($name, $middleware);
    }
}
