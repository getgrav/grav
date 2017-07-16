<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

abstract class Getters implements \ArrayAccess, \Countable
{
    /**
     * Define variable used in getters.
     *
     * @var string
     */
    protected $gettersVariable = null;

    /**
     * Magic setter method
     *
     * @param mixed $offset Medium name value
     * @param mixed $value  Medium value
     */
    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    /**
     * Magic getter method
     *
     * @param  mixed $offset Medium name value
     *
     * @return mixed         Medium value
     */
    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    /**
     * Magic method to determine if the attribute is set
     *
     * @param  mixed $offset Medium name value
     *
     * @return boolean         True if the value is set
     */
    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    /**
     * Magic method to unset the attribute
     *
     * @param mixed $offset The name value to unset
     */
    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return isset($this->{$var}[$offset]);
        } else {
            return isset($this->{$offset});
        }
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;

            return isset($this->{$var}[$offset]) ? $this->{$var}[$offset] : null;
        } else {
            return isset($this->{$offset}) ? $this->{$offset} : null;
        }
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
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
     * @param mixed $offset
     */
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
    public function count()
    {
        if ($this->gettersVariable) {
            $var = $this->gettersVariable;
            count($this->{$var});
        } else {
            count($this->toArray());
        }
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
        } else {
            $properties = (array)$this;
            $list = [];
            foreach ($properties as $property => $value) {
                if ($property[0] != "\0") {
                    $list[$property] = $value;
                }
            }

            return $list;
        }
    }
}
