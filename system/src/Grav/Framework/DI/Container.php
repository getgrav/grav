<?php

/**
 * @package    Grav\Framework\DI
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\DI;

use Psr\Container\ContainerInterface;

class Container extends \Pimple\Container implements ContainerInterface
{
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    public function has($id): bool
    {
        return $this->offsetExists($id);
    }
}