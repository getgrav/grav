<?php
namespace Grav\Common;

use Symfony\Component\Yaml\Yaml;

/**
 * Class Iterator
 * @package Grav\Common
 */
class Iterator implements \ArrayAccess, \Iterator, \Countable, \Serializable
{
    /**
     * @var array
     */
    protected $items = array();

    /**
     * @var bool
     */
    protected $unset = false;

    /**
     * Constructor.
     *
     * @param  array  $items  Initial items inside the iterator.
     */
    public function __construct(array $items = array())
    {
        $this->items = $items;
    }

    /**
     * Convert function calls for the existing keys into their values.
     *
     * @param  string $key
     * @param  mixed  $args
     * @return mixed
     */
    public function __call($key, $args)
    {
        return (isset($this->items[$key])) ? $this->items[$key] : null;
    }

    /**
     * Array getter shorthand to get items.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return (isset($this->items[$key])) ? $this->items[$key] : null;
    }

    /**
     * Array setter shorthand to set the value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * Array isset shorthand to set the value.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->items[$key]);
    }

    /**
     * Array unset shorthand to remove the key.
     *
     * @param string $key
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
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
     * @todo Add support to nested sets.
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
     * @return mixed|bool
     */
    public function nth($key)
    {
        $items = array_values($this->items);
        return (isset($items[$key])) ? $this->offsetGet($items[$key]) : false;
    }

    /**
     * @param mixed $needle Searched value.
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

        $new = array();
        foreach($keys as $key) {
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
     * @param int $num  Specifies how many entries should be picked.
     * @return $this
     */
    public function random($num = 1)
    {
        $this->items = array_intersect_key($this->items, array_flip((array) array_rand($this->items, $num)));

        return $this;
    }

    /**
     * Append new elements to the list.
     *
     * @param array|Iterator $items  Items to be appended. Existing keys will be overridden with the new values.
     * @return $this
     */
    public function append($items)
    {
        if ($items instanceof static) {
            $items = $items->toArray();
        }
        $this->items = array_merge($this->items, (array) $items);

        return $this;
    }

    // Implements export functions to array, YAML and JSON.

    /**
     * Return items as an array.
     *
     * @return array  Array presentation of the iterator.
     */
    public function toArray()
    {
        return $this->items;
    }

    /**
     * Return YAML encoded string of items.
     *
     * @return string  YAML presentation of the iterator.
     */
    public function toYaml()
    {
        return Yaml::dump($this->items);
    }

    /**
     * Return JSON encoded string of items.
     *
     * @return string  JSON presentation of the iterator.
     */
    public function toJson()
    {
        return json_encode($this->items);
    }

    // Implements Iterator.

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
        if ($this->unset) {
            // If current item was unset, position is already in the next element (do nothing).
            $this->unset = false;
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
        $this->unset = false;
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

    // Implements ArrayAccess

    /**
     * Whether or not an offset exists.
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unsets an offset.
     *
     * @param mixed $offset  The offset to unset.
     */
    public function offsetUnset($offset)
    {
        if ($offset == key($this->items)) {
            $this->unset = true;
        }
        unset($this->items[$offset]);
    }

    // Implements Countable

    /**
     * This method is executed when using the count() function.
     *
     * @return int  The count of items.
     */
    public function count()
    {
        return count($this->items);
    }

    // Implements Serializable

    /**
     * Returns string representation of the object.
     *
     * @return string  Returns the string representation of the object.
     */
    public function serialize()
    {
        return serialize($this->items);
    }

    /**
     * Called during unserialization of the object.
     *
     * @param string $serialized  The string representation of the object.
     */
    public function unserialize($serialized)
    {
        $this->items = unserialize($serialized);
    }
}
