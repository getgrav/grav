<?php

/**
 * @package    Grav\Framework\DI
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\DI;

use Psr\Container\ContainerInterface;

class Container extends \Pimple\Container implements ContainerInterface
{
    /**
     * @param string $id
     * @return mixed
     */
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id): bool
    {
        return $this->offsetExists($id);
    }

    /**
     * Magic property access — bridges `$container->service` to `$container['service']`.
     * Enables IDE autocomplete via `@property-read` annotations on subclasses.
     *
     * @noinspection MagicMethodsValidityInspection
     */
    public function __get(string $id): mixed
    {
        return $this->get($id);
    }

    /** @noinspection MagicMethodsValidityInspection */
    public function __isset(string $id): bool
    {
        return $this->has($id);
    }
}
