<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class AssetsProcessor
 * @package Grav\Common\Processors
 */
class AssetsProcessor extends ProcessorBase
{
    /** @var string */
    public $id = '_assets';
    /** @var string */
    public $title = 'Assets';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();
        $this->container['assets']->init();
        $this->container->fireEvent('onAssetsInitialized');
        $this->stopTimer();

        return $handler->handle($request);
    }
}
