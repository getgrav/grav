<?php

declare(strict_types=1);

namespace Grav\Framework\RequestHandler;

use Grav\Framework\RequestHandler\Exception\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    /** @var string[]|MiddlewareInterface[]|array */
    protected $middleware;

    /** @var callable */
    private $default;

    /** @var ContainerInterface */
    private $container;

    /** @var callable */
    private $wrapper;

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
        $this->default = $default;
        $this->container = $container;
    }

    /**
     * @param callable $wrapper
     * @return $this
     */
    public function setWrapper(callable $wrapper)
    {
        $this->wrapper = $wrapper;

        return $this;
    }

    /**
     * @param $name
     * @param callable $callable
     * @return $this
     */
    public function add($name, callable $callable)
    {
        $this->container[$name] = $callable;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = array_shift($this->middleware);

        // Use default callable if there is no middleware.
        if ($middleware === null) {
            return \call_user_func($this->default, $request);
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, clone $this);
        }

        if (!$this->container || !$this->container->has($middleware)) {
            throw new InvalidArgumentException(
                sprintf('The middleware is not a valid %s and is not passed in the Container', MiddlewareInterface::class),
                $middleware
            );
        }

        array_unshift($this->middleware, $this->container->get($middleware));

        return $this->handle($request);
    }
}