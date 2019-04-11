<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DebuggerAssetsProcessor extends ProcessorBase
{
    public $id = 'debugger_assets';
    public $title = 'Debugger Assets';

    public function process(ServerRequestInterface $request = null, RequestHandlerInterface $handler = null) : ResponseInterface
    {
        $this->startTimer();
        $this->container['debugger']->addAssets();
        $this->stopTimer();

        // Backwards compatibility
        if ($request && $handler) {
            return $handler->handle($request);
        } else {
            return new Response();
        }
    }
}
