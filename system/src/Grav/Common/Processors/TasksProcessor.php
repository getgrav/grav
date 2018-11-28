<?php

/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Framework\RequestHandler\Exception\NotFoundException;
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
            $attributes = $request->getAttribute('controller');
            $controllerClass = $attributes['class'] ?? null;
            if ($controllerClass) {
                /** @var RequestHandlerInterface $controller */
                $controller = new $controllerClass($attributes['path'] ?? '', $attributes['params'] ?? []);
                try {
                    return $controller->handle($request);
                } catch (NotFoundException $e) {
                    // Task not found: Let it pass through.
                }
            }

            $this->container->fireEvent('onTask.' . $task);
        }
        $this->stopTimer();

        return $handler->handle($request);
    }
}
