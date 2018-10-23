<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccessWithGetters;
use RocketTheme\Toolbox\ArrayTraits\Iterator as ArrayIterator;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\Serializable;

class Iterator implements \ArrayAccess, \Iterator, \Countable, \Serializable
{
    use Constructor, ArrayAccessWithGetters, ArrayIterator, Countable, Serializable, Export;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * Convert function calls for the existing keys into their values.
     *
     * @param  string $key
     * @param  mixed  $args
     *
     * @return mixed
     */
    public function __call($key, $args)
    {
        return (isset($this->items[$key])) ? $this->items[$key] : null;
    }

    /**
     * Clone the iterator.
     */
    public function __clone()
    {
        foreach ($this as $key => $value) {
            if (is_object($value)) {
                $this->$key = clone $this->$key;
            }
        }
    }

    /**
     * Convents iterator to a comma separated list.
     *
     * @return string
     */
    public function __toString()
    {
        return implode(',', $this->items);
    }

    /**
     * Remove item from the list.
     *
     * @param $key
     */
    public function remove($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Return previous item.
     *
     * @return mixed
     */
    public function prev()
    {
        return prev($this->items);
    }

    /**
     * Return nth item.
     *
     * @param int $key
     *
     * @return mixed|bool
     */
    public function nth($key)
    {
        $items = array_keys($this->items);

        return (isset($items[$key])) ? $this->offsetGet($items[$key]) : false;
    }

    /**
     * Get the first item
     *
     * @return mixed
     */
    public function first()
    {
        $items = array_keys($this->items);

        return $this->offsetGet(array_shift($items));
    }

    /**
     * Get the last item
     *
     * @return mixed
     */
    public function last()
    {
        $items = array_keys($this->items);

        return $this->offsetGet(array_pop($items));
    }

    /**
     * Reverse the Iterator
     *
     * @return $this
     */
    public function reverse()
    {
        $this->items = array_reverse($this->items);

        return $this;
    }

    /**
     * @param mixed $needle Searched value.
     *
     * @return string|bool  Key if found, otherwise false.
     */
    public function indexOf($needle)
    {
        foreach (array_values($this->items) as $key => $value) {
            if ($value === $needle) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Shuffle items.
     *
     * @return $this
     */
    public function shuffle()
    {
        $keys = array_keys($this->items);
        shuffle($keys);

        $new = [];
        foreach ($keys as $key) {
            $new[$key] = $this->items[$key];
        }

        $this->items = $new;

        return $this;
    }

    /**
     * Slice the list.
     *
     * @param int $offset
     * @param int $length
     *
     * @return $this
     */
    public function slice($offset, $length = null)
    {
        $this->items = array_slice($this->items, $offset, $length);

        return $this;
    }

    /**
     * Pick one or more random entries.
     *
     * @param int $num Specifies how many entries should be picked.
     *
     * @return $this
     */
    public function random($num = 1)
    {
        if ($num > count($this->items)) {
            $num = count($this->items);
        }

        $this->items = array_intersect_key($this->items, array_flip((array)array_rand($this->items, $num)));

        return $this;
    }

    /**
     * Append new elements to the list.
     *
     * @param array|Iterator $items Items to be appended. Existing keys will be overridden with the new values.
     *
     * @return $this
     */
    public function append($items)
    {
        if ($items instanceof static) {
            $items = $items->toArray();
        }
        $this->items = array_merge($this->items, (array)$items);

        return $this;
    }

    /**
     * Filter elements from the list
     *
     * @param  callable|null $callback A function the receives ($value, $key) and must return a boolean to indicate
     *                                 filter status
     *
     * @return $this
     */
    public function filter(callable $callback = null)
    {
        foreach ($this->items as $key => $value) {
            if (
                ($callback && !call_user_func($callback, $value, $key)) ||
                (!$callback && !(bool)$value)
            ) {
                unset($this->items[$key]);
            }
        }

        return $this;
    }


    /**
     * Sorts elements from the list and returns a copy of the list in the proper order
     *
     * @param callable|null $callback
     *
     * @param bool          $desc
     *
     * @return $this|array
     * @internal param bool $asc
     *
     */
    public function sort(callable $callback = null, $desc = false)
    {
        if (!$callback || !is_callable($callback)) {
            return $this;
        }

        $items = $this->items;
        uasort($items, $callback);

        return !$desc ? $items : array_reverse($items, true);
    }
}
