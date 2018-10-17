<?php

/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SiteSetupProcessor extends ProcessorBase
{
    public $id = '_setup';
    public $title = 'Site Setup';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();
        $this->container['request'];
        $this->container['setup']->init();
        $this->container['streams'];
        $this->stopTimer();

        return $handler->handle($request);
    }
}
