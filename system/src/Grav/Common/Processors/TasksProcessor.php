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

class TasksProcessor extends ProcessorBase
{
    public $id = 'tasks';
    public $title = 'Tasks';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $this->startTimer();
        $task = $this->container['task'];
        if ($task) {
            $this->container->fireEvent('onTask.' . $task);
        }
        $this->stopTimer();

        return $handler->handle($request);
    }
}
