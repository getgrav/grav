<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DebuggerAssetsProcessor extends ProcessorBase
{
    /** @var string */
    public $id = 'debugger_assets';
    /** @var string */
    public $title = 'Debugger Assets';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();
        $this->container['debugger']->addAssets();
        $this->stopTimer();

        return $handler->handle($request);
    }
}
