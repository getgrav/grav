<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccessWithGetters;
use RocketTheme\Toolbox\ArrayTraits\Export;

/**
 * Object class.
 *
 * @package Grav\Framework\Object
 */
class Object implements ObjectInterface
{
    use ObjectTrait, ArrayAccessWithGetters, Export {
        ArrayAccessWithGetters::offsetExists as private parentOffsetExists;
        ArrayAccessWithGetters::offsetGet as private parentOffsetGet;
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
     * Checks whether or not an offset exists with a possibility to load the field by $this->loadField{offset}().
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function offsetExists($offset)
    {
        return $this->parentOffsetExists($offset) || method_exists($this, "loadOffset_{$offset}");
    }

    /**
     * Returns the value at specified offset with a possibility to load the field by $this->loadField{offset}().
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        if (!$this->parentOffsetExists($offset) && method_exists($this, "loadOffset_{$offset}")) {
            $this->offsetSet($offset, call_user_func([$this, "loadOffset_{$offset}"]));
        }

        return $this->parentOffsetGet($offset);
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
