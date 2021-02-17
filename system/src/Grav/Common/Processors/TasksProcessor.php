<?php

/**
 * @package    Grav\Common\Processors
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Framework\RequestHandler\Exception\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class TasksProcessor
 * @package Grav\Common\Processors
 */
class TasksProcessor extends ProcessorBase
{
    /** @var string */
    public $id = 'tasks';
    /** @var string */
    public $title = 'Tasks';

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startTimer();

        $task = $this->container['task'];
        $action = $this->container['action'];
        if ($task || $action) {
            $attributes = $request->getAttribute('controller');

            $controllerClass = $attributes['class'] ?? null;
            if ($controllerClass) {
                /** @var RequestHandlerInterface $controller */
                $controller = new $controllerClass($attributes['path'] ?? '', $attributes['params'] ?? []);
                try {
                    $response = $controller->handle($request);

                    if ($response->getStatusCode() === 418) {
                        $response = $handler->handle($request);
                    }

                    $this->stopTimer();

                    return $response;
                } catch (NotFoundException $e) {
                    // Task not found: Let it pass through.
                }
            }

            if ($task) {
                $this->container->fireEvent('onTask.' . $task);
            } elseif ($action) {
                $this->container->fireEvent('onAction.' . $action);
            }
        }
        $this->stopTimer();

        return $handler->handle($request);
    }
}
