<?php
namespace Grav\Component\ArrayTraits;

/**
 * Implements Iterator interface
 * @package Grav\Component\ArrayTraits
 *
 * @property array $items
 */
trait Iterator
{
    /**
     * Hack to make Iterator work together with unset().
     *
     * @var bool
     */
    private $iteratorUnset = false;

    /**
     * Returns the current element.
     *
     * @return mixed  Can return any type.
     */
    public function current()
    {
        return current($this->items);
    }

    /**
     * Returns the key of the current element.
     *
     * @return mixed  Returns scalar on success, or NULL on failure.
     */
    public function key()
    {
        return key($this->items);
    }

    /**
     * Moves the current position to the next element.
     *
     * @return void
     */
    public function next()
    {
        if ($this->iteratorUnset) {
            // If current item was unset, position is already in the next element (do nothing).
            $this->iteratorUnset = false;
        } else {
            next($this->items);
        }
    }

    /**
     * Rewinds back to the first element of the Iterator.
     *
     * @return void
     */
    public function rewind()
    {
        $this->iteratorUnset = false;
        reset($this->items);
    }

    /**
     * This method is called after Iterator::rewind() and Iterator::next() to check if the current position is valid.
     *
     * @return bool  Returns TRUE on success or FALSE on failure.
     */
    public function valid()
    {
        return key($this->items) !== null;
    }
}
