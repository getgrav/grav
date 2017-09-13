<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Property;

/**
 * Mixed Property Trait
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
        ArrayPropertyTrait::getElements as getArrayElements;
        ArrayPropertyTrait::setElements as setArrayElements;
        ObjectPropertyTrait::doHasProperty as hasObjectProperty;
        ObjectPropertyTrait::doGetProperty as getObjectProperty;
        ObjectPropertyTrait::doSetProperty as setObjectProperty;
        ObjectPropertyTrait::doUnsetProperty as unsetObjectProperty;
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
     * @param string $value         New value.
     * @return $this
     */
    protected function doSetProperty($property, $value)
    {
        $this->hasObjectProperty($property)
            ? $this->setObjectProperty($property, $value) : $this->setArrayProperty($property, $value);

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
     * @return array
     */
    protected function getElements()
    {
        return $this->getObjectElements() + $this->getArrayElements();
    }

    /**
     * @param array $elements
     */
    protected function setElements(array $elements)
    {
        $this->setObjectElements(array_intersect_key($elements, $this->_definedProperties));
        $this->setArrayElements(array_diff_key($elements, $this->_definedProperties));
    }
}
