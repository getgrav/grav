<?php
namespace Grav\Common\Collection;

interface CollectionInterface extends \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * Add item to the list.
     *
     * @param mixed $item
     * @param string $key
     * @return $this
     */
    public function add($item, $key = null);

    /**
     * Remove item from the list.
     *
     * @param $key
     */
    public function remove($key);
}
