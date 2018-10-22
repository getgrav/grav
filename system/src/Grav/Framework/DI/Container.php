<?php

declare(strict_types=1);

namespace Grav\Framework\DI;

use Psr\Container\ContainerInterface;

class Container extends \Pimple\Container implements ContainerInterface
{
    public function get($id)
    {
        return $this->offsetGet($id);
    }

    public function has($id)
    {
        return $this->offsetExists($id);
    }
}