<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Property;

/**
 * Object Property Trait
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
                $this->{$property} = $this->onPropertyLoad($property, $doCreate());
            } else {
                return $default;
            }
        }

        return $this->{$property};
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string $value         New value.
     * @throws \InvalidArgumentException
     */
    protected function doSetProperty($property, $value)
    {
        if (!array_key_exists($property, $this->_definedProperties)) {
            throw new \InvalidArgumentException("Property '{$property}' does not exist in the object!");
        }

        $this->_definedProperties[$property] = true;
        $this->{$property} = $this->onPropertySet($property, $value);
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


    protected function onPropertyLoad($offset, $value)
    {
        $methodName = "offsetLoad_{$offset}";

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}($value);
        }

        return $value;
    }

    protected function onPropertySet($offset, $value)
    {
        $methodName = "offsetPrepare_{$offset}";

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}($value);
        }

        return $value;
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
     * @return array
     */
    protected function getElements()
    {
        $properties = array_intersect_key(get_object_vars($this), array_filter($this->_definedProperties));

        $elements = [];
        foreach ($properties as $offset => $value) {
            $methodName = "offsetSerialize_{$offset}";
            if (method_exists($this, $methodName)) {
                $elements[$offset] = $this->{$methodName}($value);
            } else {
                $elements[$offset] = $value;
            }
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
