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
     * Properties of the object.
     * @var array
     */
    protected $items;

    /**
     * @var string
     */
    private $key;

    /**
     * @param array $elements
     * @param string $key
     */
    public function __construct(array $elements = [], $key = null)
    {

        $this->items = $elements;
        $this->key = $key !== null ? $key : $this->getKey();

        if ($this->key === null) {
            throw new \InvalidArgumentException('Object cannot be created without assigning a key');
        }
    }

    /**
     * Checks whether or not an offset exists with a possibility to load the field by $this->offsetLoad_{$offset}().
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function offsetExists($offset)
    {
        return $this->parentOffsetExists($offset) || method_exists($this, "offsetLoad_{$offset}");
    }

    /**
     * Returns the value at specified offset with a possibility to load the field by $this->offsetLoad_{$offset}().
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        if (!$this->parentOffsetExists($offset) && method_exists($this, "offsetLoad_{$offset}")) {
            $this->offsetSet($offset, call_user_func([$this, "offsetLoad_{$offset}"]));
        }

        return $this->parentOffsetGet($offset);
    }


    /**
     * Assigns a value to the specified offset with a possibility to check or alter the value by $this->offsetPrepare_{$offset}().
     *
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     */
    public function offsetSet($offset, $value)
    {
        if (method_exists($this, "offsetPrepare_{$offset}")) {
            $value = call_user_func([$this, "offsetPrepare_{$offset}"], $value);
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
