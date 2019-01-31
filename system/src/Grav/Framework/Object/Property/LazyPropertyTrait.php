<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Property;

/**
 * Lazy Mixed Property Trait
 *
 * Stores defined object properties as class member variables and the rest into an array. Object properties are lazy
 * loaded from the array.
 *
 * You may define following methods for the member variables:
 * - `$this->offsetLoad($offset, $value)` called first time object property gets accessed
 * - `$this->offsetPrepare($offset, $value)` called on every object property set
 * - `$this->offsetSerialize($offset, $value)` called when the raw or serialized object property value is needed
 *
 * @package Grav\Framework\Object\Property
 */
trait LazyPropertyTrait
{
    use ArrayPropertyTrait, ObjectPropertyTrait {
        ObjectPropertyTrait::__construct insteadof ArrayPropertyTrait;
        ArrayPropertyTrait::doHasProperty as hasArrayProperty;
        ArrayPropertyTrait::doGetProperty as getArrayProperty;
        ArrayPropertyTrait::doSetProperty as setArrayProperty;
        ArrayPropertyTrait::doUnsetProperty as unsetArrayProperty;
        ArrayPropertyTrait::getElement as getArrayElement;
        ArrayPropertyTrait::getElements as getArrayElements;
        ArrayPropertyTrait::setElements insteadof ObjectPropertyTrait;
        ObjectPropertyTrait::doHasProperty as hasObjectProperty;
        ObjectPropertyTrait::doGetProperty as getObjectProperty;
        ObjectPropertyTrait::doSetProperty as setObjectProperty;
        ObjectPropertyTrait::doUnsetProperty as unsetObjectProperty;
        ObjectPropertyTrait::getElement as getObjectElement;
        ObjectPropertyTrait::getElements as getObjectElements;
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
     * @return mixed                Property value.
     */
    protected function &doGetProperty($property, $default = null, $doCreate = false)
    {
        if ($this->hasObjectProperty($property)) {
            return $this->getObjectProperty($property, $default, function ($default = null) use ($property) {
                return $this->getArrayProperty($property, $default);
            });
        }

        return $this->getArrayProperty($property, $default, $doCreate);
    }

    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $value         New value.
     * @return $this
     */
    protected function doSetProperty($property, $value)
    {
        if ($this->hasObjectProperty($property)) {
            $this->setObjectProperty($property, $value);
        } else {
            $this->setArrayProperty($property, $value);
        }

        return $this;
    }

    /**
     * @param string  $property     Object property to be unset.
     * @return $this
     */
    protected function doUnsetProperty($property)
    {
        $this->hasObjectProperty($property) ?
            $this->unsetObjectProperty($property) : $this->unsetArrayProperty($property);

        return $this;
    }

    /**
     * @param string $property
     * @param mixed|null $default
     * @return mixed|null
     */
    protected function getElement($property, $default = null)
    {
        if ($this->isPropertyLoaded($property)) {
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
}
