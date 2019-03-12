<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DebuggerProcessor extends ProcessorBase
{
    public $id = '_debugger';
    public $title = 'Init Debugger';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();
        $this->container['debugger']->init();
        $this->stopTimer();

        return $handler->handle($request);
    }
}
