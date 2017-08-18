<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;

/**
 * Object class.
 *
 * @package Grav\Framework\Object
 */
class Object implements ObjectInterface
{
    use ObjectTrait, NestedArrayAccessWithGetters, Export {
        NestedArrayAccessWithGetters::offsetExists as private parentOffsetExists;
        NestedArrayAccessWithGetters::offsetGet as private parentOffsetGet;
        NestedArrayAccessWithGetters::offsetSet as private parentOffsetSet;
    }

    /**
     * Checks whether or not an offset exists with a possibility to load the field by $this->offsetLoad_{$offset}().
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function offsetExists($offset)
    {
        $methodName = "offsetLoad_{$offset}";

        return $this->parentOffsetExists($offset) || method_exists($this, $methodName);
    }

    /**
     * Returns the value at specified offset with a possibility to load the field by $this->offsetLoad_{$offset}().
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        $methodName = "offsetLoad_{$offset}";

        if (!$this->parentOffsetExists($offset) && method_exists($this, $methodName)) {
            $this->offsetSet($offset, $this->{$methodName}());
        }

        return $this->parentOffsetGet($offset);
    }


    /**
     * Assigns a value to the specified offset with a possibility to check or alter the value by
     * $this->offsetPrepare_{$offset}().
     *
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     */
    public function offsetSet($offset, $value)
    {
        $methodName = "offsetPrepare_{$offset}";

        if (method_exists($this, $methodName)) {
            $value = $this->{$methodName}($value);
        }

        $this->parentOffsetSet($offset, $value);
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return ['key' => $this->getKey(), 'object' => $this->toArray()];
    }
}
