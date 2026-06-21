<?php

declare(strict_types=1);

/*
 * This file is part of Pimple.
 *
 * Copyright (c) 2009 Fabien Potencier
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Pimple;

use ArrayAccess;
use Pimple\Exception\ExpectedInvokableException;
use Pimple\Exception\FrozenServiceException;
use Pimple\Exception\InvalidServiceIdentifierException;
use Pimple\Exception\UnknownIdentifierException;
use SplObjectStorage;
use function array_keys;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function trigger_error;

/**
 * Container main class.
 *
 * @author Fabien Potencier
 */
class Container implements ArrayAccess
{
    /** @var array<string,mixed> */
    private array $values = [];

    private SplObjectStorage $factories;

    private SplObjectStorage $protected;

    /** @var array<string,bool> */
    private array $frozen = [];

    /** @var array<string,mixed> */
    private array $raw = [];

    /** @var array<string,bool> */
    private array $keys = [];

    /**
     * Instantiates the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects
     */
    public function __construct(array $values = [])
    {
        $this->factories = new SplObjectStorage();
        $this->protected = new SplObjectStorage();

        foreach ($values as $key => $value) {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * Sets a parameter or an object.
     *
     * Objects must be defined as Closures.
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same name as an existing parameter would break your container).
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to define an object
     *
     * @return void
     *
     * @throws FrozenServiceException Prevent override of a frozen service
     */
    public function offsetSet(mixed $id, mixed $value): void
    {
        if (!is_string($id)) {
            throw new InvalidServiceIdentifierException($id);
        }

        if (isset($this->frozen[$id])) {
            throw new FrozenServiceException($id);
        }

        $this->values[$id] = $value;
        $this->keys[$id] = true;
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws UnknownIdentifierException If the identifier is not defined
     */
    public function offsetGet(mixed $id): mixed
    {
        if (!is_string($id)) {
            throw new InvalidServiceIdentifierException($id);
        }

        if (!isset($this->keys[$id])) {
            throw new UnknownIdentifierException($id);
        }

        if (
            isset($this->raw[$id])
            || !is_object($this->values[$id])
            || isset($this->protected[$this->values[$id]])
            || !method_exists($this->values[$id], '__invoke')
        ) {
            return $this->values[$id];
        }

        if (isset($this->factories[$this->values[$id]])) {
            return $this->values[$id]($this);
        }

        $raw = $this->values[$id];
        $val = $this->values[$id] = $raw($this);
        $this->raw[$id] = $raw;

        $this->frozen[$id] = true;

        return $val;
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return bool
     */
    public function offsetExists(mixed $id): bool
    {
        return is_string($id) && isset($this->keys[$id]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return void
     */
    public function offsetUnset(mixed $id): void
    {
        if (!is_string($id)) {
            throw new InvalidServiceIdentifierException($id);
        }

        if (isset($this->keys[$id])) {
            if (is_object($this->values[$id])) {
                unset($this->factories[$this->values[$id]], $this->protected[$this->values[$id]]);
            }

            unset($this->values[$id], $this->frozen[$id], $this->raw[$id], $this->keys[$id]);
        }
    }

    /**
     * Marks a callable as being a factory service.
     *
     * @param callable $callable A service definition to be used as a factory
     *
     * @return callable The passed callable
     *
     * @throws ExpectedInvokableException Service definition has to be a closure or an invokable object
     */
    public function factory(object $callable): object
    {
        if (!method_exists($callable, '__invoke')) {
            throw new ExpectedInvokableException('Service definition is not a Closure or invokable object.');
        }

        $this->factories->attach($callable);

        return $callable;
    }

    /**
     * Protects a callable from being interpreted as a service.
     *
     * This is useful when you want to store a callable as a parameter.
     *
     * @param callable $callable A callable to protect from being evaluated
     *
     * @return callable The passed callable
     *
     * @throws ExpectedInvokableException Service definition has to be a closure or an invokable object
     */
    public function protect(object $callable): object
    {
        if (!method_exists($callable, '__invoke')) {
            throw new ExpectedInvokableException('Callable is not a Closure or invokable object.');
        }

        $this->protected->attach($callable);

        return $callable;
    }

    /**
     * Gets a parameter or the closure defining an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws UnknownIdentifierException If the identifier is not defined
     */
    public function raw(string $id): mixed
    {
        if (!isset($this->keys[$id])) {
            throw new UnknownIdentifierException($id);
        }

        if (isset($this->raw[$id])) {
            return $this->raw[$id];
        }

        return $this->values[$id];
    }

    /**
     * Extends an object definition.
     *
     * Useful when you want to extend an existing object definition,
     * without necessarily loading that object.
     *
     * @param string   $id       The unique identifier for the object
     * @param callable $callable A service definition to extend the original
     *
     * @return callable The wrapped callable
     *
     * @throws UnknownIdentifierException        If the identifier is not defined
     * @throws FrozenServiceException            If the service is frozen
     * @throws InvalidServiceIdentifierException If the identifier belongs to a parameter
     * @throws ExpectedInvokableException        If the extension callable is not a closure or an invokable object
     */
    public function extend(string $id, object $callable): object
    {
        if (!isset($this->keys[$id])) {
            throw new UnknownIdentifierException($id);
        }

        if (isset($this->frozen[$id])) {
            throw new FrozenServiceException($id);
        }

        if (!is_object($this->values[$id]) || !method_exists($this->values[$id], '__invoke')) {
            throw new InvalidServiceIdentifierException($id);
        }

        if (isset($this->protected[$this->values[$id]])) {
            @trigger_error(sprintf('How Pimple behaves when extending protected closures will be fixed in Pimple 4. Are you sure "%s" should be protected?', $id), E_USER_DEPRECATED);
        }

        if (!method_exists($callable, '__invoke')) {
            throw new ExpectedInvokableException('Extension service definition is not a Closure or invokable object.');
        }

        $factory = $this->values[$id];

        $extended = function (self $container) use ($callable, $factory): mixed {
            return $callable($factory($container), $container);
        };

        if (isset($this->factories[$factory])) {
            $this->factories->detach($factory);
            $this->factories->attach($extended);
        }

        return $this[$id] = $extended;
    }

    /**
     * Returns all defined value names.
     *
     * @return array An array of value names
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    /**
     * Registers a service provider.
     *
     * @param array $values An array of values that customizes the provider
     *
     * @return static
     */
    public function register(ServiceProviderInterface $provider, array $values = []): static
    {
        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }
}
