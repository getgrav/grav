<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use ArrayAccess;
use Countable;
use function count;

/**
 * Class Getters
 * @package Grav\Common
 */
abstract class Getters implements ArrayAccess, Countable
{
    /** @var string Define variable used in getters. */
    protected $gettersVariable = null;

    /**
     * Magic setter method
     *
     * @param int|string $offset Medium name value
     * @param mixed $value  Medium value
     */
    #[\ReturnTypeWillChange]
    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    /**
     * Magic getter method
     *
     * @param  int|string $offset Medium name value
     * @return mixed         Medium value
     */
    #[\ReturnTypeWillChange]
    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    /**
     * Magic method to determine if the attribute is set
     *
     * @param  int|string $offset Medium name value
     * @return boolean         True if the value is set
     */
    #[\ReturnTypeWillChange]
    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    /**
     * Magic method to unset the attribute
     *
     * @param int|string $offset The name value to unset
     */
    #[\ReturnTypeWillChange]
    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }

    /**
     * @param int|string $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return isset($this->{$var}[$offset]);
        }

        return isset($this->{$offset});
    }

    /**
     * @param int|string $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return $this->{$var}[$offset] ?? null;
        }

        return $this->{$offset} ?? null;
    }

    /**
     * @param int|string $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            $this->{$var}[$offset] = $value;
        } else {
            $this->{$offset} = $value;
        }
    }

    /**
     * @param int|string $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            unset($this->{$var}[$offset]);
        } else {
            unset($this->{$offset});
        }
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            return count($this->{$var});
        }

        return count($this->toArray());
    }

    /**
     * Returns an associative array of object properties.
     *
     * @return array
     */
    public function toArray()
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return $this->{$var};
        }

        $properties = (array)$this;
        $list = [];
        foreach ($properties as $property => $value) {
            if ($property[0] !== "\0") {
                $list[$property] = $value;
            }
        }

        return $list;
    }
}
