<?php
namespace Grav\Component\ArrayTraits;

/**
 * Class Countable
 * @package Grav\Component\ArrayTraits
 *
 * @property array $items;
 */
trait Countable
{
    /**
     * @return int
     */
    public function count()
    {
        count($this->items);
    }
}
