<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * Object class.
 *
 * @package Grav\Framework\Object
 */
class Object implements ObjectInterface
{
    use ObjectTrait;

    static protected $prefix = 'o.';
    static protected $type;

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->get('this.is.my.nested.variable');
     *
     * @param string  $property   Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     * @return mixed  Value.
     */
    public function getProperty($property, $default = null, $separator = '.')
    {
        $path = explode($separator, $property);
        $offset = array_shift($path);
        $current = $this->__get($offset);

        do {
            // We are done: return current variable.
            if (empty($path)) {
                return $current;
            }

            // Get property of nested Object.
            if ($current instanceof Object) {
                return $current->getProperty(implode($separator, $path), $default, $separator);
            }

            $offset = array_shift($path);

            if ((is_array($current) || is_a($current, 'ArrayAccess')) && isset($current[$offset])) {
                $current = $current[$offset];
            } elseif (is_object($current) && isset($current->{$offset})) {
                $current = $current->{$offset};
            } else {
                return $default;
            }
        } while ($path);

        return $current;
    }

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @example $data->set('this.is.my.nested.variable', $value);
     *
     * @param string  $property   Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function setProperty($property, $value, $separator = '.')
    {
        $path = explode($separator, $property);
        $offset = array_shift($path);

        // Set simple variable.
        if (empty($path)) {
            $this->__set($offset, $value);

            return $this;
        }

        $current = &$this->getRef($offset, true);

        do {
            // Set property of nested Object.
            if ($current instanceof Object) {
                $current->setProperty(implode($separator, $path), $value, $separator);

                return $this;
            }

            $offset = array_shift($path);

            if (is_object($current)) {
                // Handle objects.
                if (!isset($current->{$offset})) {
                    $current->{$offset} = [];
                }
                $current = &$current->{$offset};
            } else {
                // Handle arrays and scalars.
                if (!is_array($current)) {
                    $current = [$offset => []];
                } elseif (!isset($current[$offset])) {
                    $current[$offset] = [];
                }
                $current = &$current[$offset];
            }
        } while ($path);

        $current = $value;

        return $this;
    }

    /**
     * Define value by using dot notation for nested arrays/objects.
     *
     * @example $data->defProperty('this.is.my.nested.variable', $value);
     *
     * @param string  $property   Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function defProperty($property, $value, $separator = '.')
    {
        $test = new \stdClass;
        if ($this->getProperty($property, $test, $separator) === $test) {
            $this->setProperty($property, $value, $separator);
        }

        return $this;
    }

    /**
     * Checks whether or not an offset exists with a possibility to load the field by $this->offsetLoad_{$offset}().
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function __isset($offset)
    {
        return array_key_exists($offset, $this->items) || $this->isPropertyDefined($offset);
    }

    /**
     * Returns the value at specified offset with a possibility to load the field by $this->offsetLoad_{$offset}().
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function __get($offset)
    {
        return $this->getRef($offset);
    }

    /**
     * Assigns a value to the specified offset with a possibility to check or alter the value by
     * $this->offsetPrepare_{$offset}().
     *
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     */
    public function __set($offset, $value)
    {
        if ($this->isPropertyDefined($offset)) {
            $methodName = "offsetPrepare_{$offset}";

            if (method_exists($this, $methodName)) {
                $this->{$offset} = $this->{$methodName}($value);
            }
        }

        $this->items[$offset] = $value;
    }

    /**
     * Magic method to unset the attribute
     *
     * @param mixed $offset The name value to unset
     */
    public function __unset($offset)
    {
        if ($this->isPropertyDefined($offset)) {
            $this->{$offset} = null;
        } else {
            unset($this->items[$offset]);
        }
    }

    /**
     * Convert object into an array.
     *
     * @return array
     */
    protected function toArray()
    {
        return $this->items;
    }

    protected function &getRef($offset, $new = false)
    {
        if ($this->isPropertyDefined($offset)) {
            if ($this->{$offset} === null) {
                $methodName = "offsetLoad_{$offset}";

                if (method_exists($this, $methodName)) {
                    $this->{$offset} = $this->{$methodName}();
                }
            }

            return $this->{$offset};
        }

        if (!isset($this->items[$offset])) {
            if (!$new) {
                $null = null;
                return $null;
            }
            $this->items[$offset] = [];
        }

        return $this->items[$offset];
    }

    protected function isPropertyDefined($offset)
    {
        return array_key_exists($offset, get_object_vars($this));
    }
}
