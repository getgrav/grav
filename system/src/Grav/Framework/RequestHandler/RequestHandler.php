<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler;

use Grav\Framework\RequestHandler\Traits\RequestHandlerTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class RequestHandler
 * @package Grav\Framework\RequestHandler
 */
class RequestHandler implements RequestHandlerInterface
{
    use RequestHandlerTrait;

    /**
     * Delegate constructor.
     *
     * @param array $middleware
     * @param callable $default
     * @param ContainerInterface|null $container
     */
    public function __construct(array $middleware, callable $default, ContainerInterface $container = null)
    {
        $this->middleware = $middleware;
        $this->handler = $default;
        $this->container = $container;
    }

    /**
     * Add callable initializing Middleware that will be executed as soon as possible.
     *
     * @param string $name
     * @param callable $callable
     * @return $this
     */
    public function addCallable(string $name, callable $callable): self
    {
        $this->container[$name] = $callable;
        array_unshift($this->middleware, $name);

        return $this;
    }

    /**
     * Add Middleware that will be executed as soon as possible.
     *
     * @param string $name
     * @param MiddlewareInterface $middleware
     * @return $this
     */
    public function addMiddleware(string $name, MiddlewareInterface $middleware): self
    {
        $this->container[$name] = $middleware;
        array_unshift($this->middleware, $name);

        return $this;
    }
}
