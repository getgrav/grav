<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Base;

use Grav\Framework\Compat\Serializable;
use InvalidArgumentException;
use function get_class;

/**
 * Object trait.
 *
 * @package Grav\Framework\Object
 */
trait ObjectTrait
{
    use Serializable;

    /** @var string */
    protected static $type;

    /** @var string */
    private $_key;

    /**
     * @return string
     */
    protected function getTypePrefix()
    {
        return '';
    }

    /**
     * @param bool $prefix
     * @return string
     */
    public function getType($prefix = true)
    {
        $type = $prefix ? $this->getTypePrefix() : '';

        if (static::$type) {
            return $type . static::$type;
        }

        $class = get_class($this);
        return $type . strtolower(substr($class, strrpos($class, '\\') + 1));
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->_key ?: $this->getType() . '@@' . spl_object_hash($this);
    }

    /**
     * @return bool
     */
    public function hasKey()
    {
        return !empty($this->_key);
    }

    /**
     * @param string $property      Object property name.
     * @return bool                 True if property has been defined (can be null).
     */
    public function hasProperty($property)
    {
        return $this->doHasProperty($property);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @return mixed                Property value.
     */
    public function getProperty($property, $default = null)
    {
        return $this->doGetProperty($property, $default);
    }

    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $value         New value.
     * @return $this
     */
    public function setProperty($property, $value)
    {
        $this->doSetProperty($property, $value);

        return $this;
    }

    /**
     * @param string  $property     Object property to be unset.
     * @return $this
     */
    public function unsetProperty($property)
    {
        $this->doUnsetProperty($property);

        return $this;
    }

    /**
     * @param string  $property     Object property to be defined.
     * @param mixed   $default      Default value.
     * @return $this
     */
    public function defProperty($property, $default)
    {
        if (!$this->hasProperty($property)) {
            $this->setProperty($property, $default);
        }

        return $this;
    }

    /**
     * @return array
     */
    final public function __serialize(): array
    {
        return $this->doSerialize();
    }

    /**
     * @param array $data
     * @return void
     */
    final public function __unserialize(array $data): void
    {
        if (method_exists($this, 'initObjectProperties')) {
            $this->initObjectProperties();
        }

        $this->doUnserialize($data);
    }

    /**
     * @return array
     */
    protected function doSerialize()
    {
        return ['key' => $this->getKey(), 'type' => $this->getType(), 'elements' => $this->getElements()];
    }

    /**
     * @param array $serialized
     * @return void
     */
    protected function doUnserialize(array $serialized)
    {
        if (!isset($serialized['key'], $serialized['type'], $serialized['elements']) || $serialized['type'] !== $this->getType()) {
            throw new InvalidArgumentException("Cannot unserialize '{$this->getType()}': Bad data");
        }

        $this->setKey($serialized['key']);
        $this->setElements($serialized['elements']);
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->doSerialize();
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString()
    {
        return $this->getKey();
    }

    /**
     * @param string $key
     * @return $this
     */
    protected function setKey($key)
    {
        $this->_key = (string) $key;

        return $this;
    }
}
