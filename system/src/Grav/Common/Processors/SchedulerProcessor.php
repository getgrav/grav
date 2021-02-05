<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use RocketTheme\Toolbox\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SchedulerProcessor
 * @package Grav\Common\Processors
 */
class SchedulerProcessor extends ProcessorBase
{
    /** @var string */
    public $id = '_scheduler';
    /** @var string */
    public $title = 'Scheduler';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();
        $scheduler = $this->container['scheduler'];
        $this->container->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));
        $this->stopTimer();

        return $handler->handle($request);
    }
}
