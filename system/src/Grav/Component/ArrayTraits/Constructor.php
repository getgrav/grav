<?php
namespace Grav\Component\ArrayTraits;

/**
 * Implements Constructor
 * @package Grav\Component\ArrayTraits
 *
 * @property array $items
 */
trait Constructor
{
    /**
     * Constructor to initialize array.
     *
     * @param  array  $items  Initial items inside the iterator.
     */
    public function __construct(array $items = array())
    {
        $this->items = $items;
    }
}
