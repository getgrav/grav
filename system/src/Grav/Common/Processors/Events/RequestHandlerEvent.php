<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors\Events;

use Grav\Framework\RequestHandler\RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use RocketTheme\Toolbox\Event\Event;

class RequestHandlerEvent extends Event
{
    public function addMiddleware($name, MiddlewareInterface $middleware)
    {
        /** @var RequestHandler $handler */
        $handler = $this['handler'];
        $handler->addMiddleware($name, $middleware);
    }
}
