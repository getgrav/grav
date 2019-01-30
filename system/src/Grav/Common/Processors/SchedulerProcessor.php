<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use RocketTheme\Toolbox\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SchedulerProcessor extends ProcessorBase
{
    public $id = '_scheduler';
    public $title = 'Scheduler';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();
        $scheduler = $this->container['scheduler'];
        $this->container->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));
        $this->stopTimer();

        return $handler->handle($request);
    }
}
