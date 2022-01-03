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
 * Class PluginsProcessor
 * @package Grav\Common\Processors
 */
class PluginsProcessor extends ProcessorBase
{
    /** @var string */
    public $id = 'plugins';
    /** @var string */
    public $title = 'Initialize Plugins';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();
        $grav = $this->container;
        $grav->fireEvent('onPluginsInitialized');
        $this->stopTimer();

        return $handler->handle($request);
    }
}
