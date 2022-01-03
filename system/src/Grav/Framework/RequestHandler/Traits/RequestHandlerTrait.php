<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler\Traits;

use Grav\Framework\RequestHandler\Exception\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use function call_user_func;

/**
 * Trait RequestHandlerTrait
 * @package Grav\Framework\RequestHandler\Traits
 */
trait RequestHandlerTrait
{
    /** @var array<int,string|MiddlewareInterface> */
    protected $middleware;

    /** @var callable */
    private $handler;

    /** @var ContainerInterface|null */
    private $container;

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->middleware);

        // Use default callable if there is no middleware.
        if ($middleware === null) {
            return call_user_func($this->handler, $request);
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, clone $this);
        }

        if (null === $this->container || !$this->container->has($middleware)) {
            throw new InvalidArgumentException(
                sprintf('The middleware is not a valid %s and is not passed in the Container', MiddlewareInterface::class),
                $middleware
            );
        }

        array_unshift($this->middleware, $this->container->get($middleware));

        return $this->handle($request);
    }
}
