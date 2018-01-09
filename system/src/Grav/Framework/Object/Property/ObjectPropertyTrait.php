<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Property;

/**
 * Object Property Trait
 *
 * Stores all properties as class member variables or object properties. All properties need to be defined as protected
 * properties. Undefined properties will throw an error.
 *
 * Additionally you may define following methods:
 * - `$this->offsetLoad($offset, $value)` called first time object property gets accessed
 * - `$this->offsetPrepare($offset, $value)` called on every object property set
 * - `$this->offsetSerialize($offset, $value)` called when the raw or serialized object property value is needed
 *
 * @package Grav\Framework\Object\Property
 */
trait ObjectPropertyTrait
{
    /**
     * @var array
     */
    private $_definedProperties;

    /**
     * @param array $elements
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        $this->initObjectProperties();
        $this->setElements($elements);
        $this->setKey($key);
    }

    /**
     * @param string $property      Object property name.
     * @return bool                 True if property has been loaded.
     */
    protected function isPropertyLoaded($property)
    {
        return !empty($this->_definedProperties[$property]);
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return mixed
     */
    protected function offsetLoad($offset, $value)
    {
        $methodName = "offsetLoad_{$offset}";

        return method_exists($this, $methodName)? $this->{$methodName}($value) : $value;
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return mixed
     */
    protected function offsetPrepare($offset, $value)
    {
        $methodName = "offsetPrepare_{$offset}";

        return method_exists($this, $methodName) ? $this->{$methodName}($value) : $value;
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return mixed
     */
    protected function offsetSerialize($offset, $value)
    {
        $methodName = "offsetSerialize_{$offset}";

        return method_exists($this, $methodName) ? $this->{$methodName}($value) : $value;
    }

    /**
     * @param string $property      Object property name.
     * @return bool                 True if property has been defined (can be null).
     */
    protected function doHasProperty($property)
    {
        return array_key_exists($property, $this->_definedProperties);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @param bool $doCreate        Set true to create variable.
     * @return mixed                Property value.
     */
    protected function &doGetProperty($property, $default = null, $doCreate = false)
    {
        if (!array_key_exists($property, $this->_definedProperties)) {
            throw new \InvalidArgumentException("Property '{$property}' does not exist in the object!");
        }

        if (empty($this->_definedProperties[$property])) {
            if ($doCreate === true) {
                $this->_definedProperties[$property] = true;
                $this->{$property} = null;
            } elseif (is_callable($doCreate)) {
                $this->_definedProperties[$property] = true;
                $this->{$property} = $this->offsetLoad($property, $doCreate());
            } else {
                return $default;
            }
        }

        return $this->{$property};
    }

    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $value         New value.
     * @throws \InvalidArgumentException
     */
    protected function doSetProperty($property, $value)
    {
        if (!array_key_exists($property, $this->_definedProperties)) {
            throw new \InvalidArgumentException("Property '{$property}' does not exist in the object!");
        }

        $this->_definedProperties[$property] = true;
        $this->{$property} = $this->offsetPrepare($property, $value);
    }

    /**
     * @param string  $property     Object property to be unset.
     */
    protected function doUnsetProperty($property)
    {
        if (!array_key_exists($property, $this->_definedProperties)) {
            return;
        }

        $this->_definedProperties[$property] = false;
        unset($this->{$property});
    }

    protected function initObjectProperties()
    {
        $this->_definedProperties = [];
        foreach (get_object_vars($this) as $property => $value) {
            if ($property[0] !== '_') {
                $this->_definedProperties[$property] = ($value !== null);
            }
        }
    }

    /**
     * @param string $property
     * @param mixed|null $default
     * @return mixed|null
     */
    protected function getElement($property, $default = null)
    {
        if (empty($this->_definedProperties[$property])) {
            return $default;
        }

        return $this->offsetSerialize($property, $this->{$property});
    }

    /**
     * @return array
     */
    protected function getElements()
    {
        $properties = array_intersect_key(get_object_vars($this), array_filter($this->_definedProperties));

        $elements = [];
        foreach ($properties as $offset => $value) {
            $elements[$offset] = $this->offsetSerialize($offset, $value);
        }

        return $elements;
    }

    /**
     * @param array $elements
     */
    protected function setElements(array $elements)
    {
        foreach ($elements as $property => $value) {
            $this->setProperty($property, $value);
        }
    }

    abstract public function setProperty($property, $value);
    abstract protected function setKey($key);
}
