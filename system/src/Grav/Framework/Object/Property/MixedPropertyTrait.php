<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Property;

/**
 * Mixed Property Trait
 *
 * Stores defined object properties as class member variables and the rest into an array.
 *
 * You may define following methods for member variables:
 * - `$this->offsetLoad($offset, $value)` called first time object property gets accessed
 * - `$this->offsetPrepare($offset, $value)` called on every object property set
 * - `$this->offsetSerialize($offset, $value)` called when the raw or serialized object property value is needed

 *
 * @package Grav\Framework\Object\Property
 */
trait MixedPropertyTrait
{
    use ArrayPropertyTrait, ObjectPropertyTrait {
        ObjectPropertyTrait::__construct insteadof ArrayPropertyTrait;
        ArrayPropertyTrait::doHasProperty as hasArrayProperty;
        ArrayPropertyTrait::doGetProperty as getArrayProperty;
        ArrayPropertyTrait::doSetProperty as setArrayProperty;
        ArrayPropertyTrait::doUnsetProperty as unsetArrayProperty;
        ArrayPropertyTrait::getElement as getArrayElement;
        ArrayPropertyTrait::getElements as getArrayElements;
        ArrayPropertyTrait::setElements as setArrayElements;
        ObjectPropertyTrait::doHasProperty as hasObjectProperty;
        ObjectPropertyTrait::doGetProperty as getObjectProperty;
        ObjectPropertyTrait::doSetProperty as setObjectProperty;
        ObjectPropertyTrait::doUnsetProperty as unsetObjectProperty;
        ObjectPropertyTrait::getElement as getObjectElement;
        ObjectPropertyTrait::getElements as getObjectElements;
        ObjectPropertyTrait::setElements as setObjectElements;
    }

    /**
     * @param string $property      Object property name.
     * @return bool                 True if property has been defined (can be null).
     */
    protected function doHasProperty($property)
    {
        return $this->hasArrayProperty($property) || $this->hasObjectProperty($property);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @param bool $doCreate
     * @return mixed                Property value.
     */
    protected function &doGetProperty($property, $default = null, $doCreate = false)
    {
        if ($this->hasObjectProperty($property)) {
            return $this->getObjectProperty($property);
        }

        return $this->getArrayProperty($property, $default, $doCreate);
    }

    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $value         New value.
     * @return void
     */
    protected function doSetProperty($property, $value)
    {
        $this->hasObjectProperty($property)
            ? $this->setObjectProperty($property, $value) : $this->setArrayProperty($property, $value);
    }

    /**
     * @param string  $property     Object property to be unset.
     * @return void
     */
    protected function doUnsetProperty($property)
    {
        $this->hasObjectProperty($property) ?
            $this->unsetObjectProperty($property) : $this->unsetArrayProperty($property);
    }

    /**
     * @param string $property
     * @param mixed|null $default
     * @return mixed|null
     */
    protected function getElement($property, $default = null)
    {
        if ($this->hasObjectProperty($property)) {
            return $this->getObjectElement($property, $default);
        }

        return $this->getArrayElement($property, $default);
    }

    /**
     * @return array
     */
    protected function getElements()
    {
        return $this->getObjectElements() + $this->getArrayElements();
    }

    /**
     * @param array $elements
     * @return void
     */
    protected function setElements(array $elements)
    {
        $this->setObjectElements(array_intersect_key($elements, $this->_definedProperties));
        $this->setArrayElements(array_diff_key($elements, $this->_definedProperties));
    }
}
