<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * Object trait.
 *
 * @package Grav\Framework\Object
 */
trait ObjectTrait
{
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
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {

        $this->items = $elements;
        $this->key = $key !== null ? $key : (string) $this;

        if ($this->key === null) {
            throw new \InvalidArgumentException('Object cannot be created without assigning a key to it');
        }
    }

    /**
     * @param bool $prefix
     * @return string
     */
    public function getType($prefix = true)
    {
        if (static::$type) {
            return ($prefix ? static::$prefix : '') . static::$type;
        }

        $class = get_class($this);
        return ($prefix ? static::$prefix : '') . strtolower(substr($class, strrpos($class, '\\') + 1));
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return ['key' => (string) $this, 'type' => $this->getType(), 'elements' => $this->toArray()];
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getKey() ?: $this->getType() . '@' . spl_object_hash($this);
    }
}
