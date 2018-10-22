<?php

/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RocketTheme\Toolbox\Event\Event;

class RequestProcessor extends ProcessorBase
{
    public $id = 'request';
    public $title = 'Request';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();
        $request = $request
            ->withAttribute('grav', $this->container)
            ->withAttribute('route', Uri::getCurrentRoute())
            ->withAttribute('referrer', $this->container['uri']->referrer());

        $event = new Event(['handler' => $handler]);
        $this->container->fireEvent('onRequestHandlerInit', $event);
        $this->stopTimer();

        return $handler->handle($request);
    }
}
