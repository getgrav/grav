<?php
namespace Grav\Component\ArrayTraits;

/**
 * Implements Countable interface
 * @package Grav\Component\ArrayTraits
 *
 * @property array $items;
 */
trait Countable
{
    /**
     * Implements Countable interface.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }
}
