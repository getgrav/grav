<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Property;

use InvalidArgumentException;
use function array_key_exists;

/**
 * Array Property Trait
 *
 * Stores all object properties into an array.
 *
 * @package Grav\Framework\Object\Property
 */
trait ArrayPropertyTrait
{
    /** @var array Properties of the object. */
    private $_elements;

    /**
     * @param array $elements
     * @param string|null $key
     * @throws InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        $this->setElements($elements);
        $this->setKey($key ?? '');
    }

    /**
     * @param string $property      Object property name.
     * @return bool                 True if property has been defined (can be null).
     */
    protected function doHasProperty($property)
    {
        return array_key_exists($property, $this->_elements);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @param bool $doCreate        Set true to create variable.
     * @return mixed                Property value.
     */
    protected function &doGetProperty($property, $default = null, $doCreate = false)
    {
        if (!array_key_exists($property, $this->_elements)) {
            if ($doCreate) {
                $this->_elements[$property] = null;
            } else {
                return $default;
            }
        }

        return $this->_elements[$property];
    }

    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $value         New value.
     * @return void
     */
    protected function doSetProperty($property, $value)
    {
        $this->_elements[$property] = $value;
    }

    /**
     * @param string  $property     Object property to be unset.
     * @return void
     */
    protected function doUnsetProperty($property)
    {
        unset($this->_elements[$property]);
    }

    /**
     * @param string $property
     * @param mixed|null $default
     * @return mixed|null
     */
    protected function getElement($property, $default = null)
    {
        return array_key_exists($property, $this->_elements) ? $this->_elements[$property] : $default;
    }

    /**
     * @return array
     */
    protected function getElements()
    {
        return array_filter($this->_elements, static function ($val) {
            return $val !== null;
        });
    }

    /**
     * @param array $elements
     * @return void
     */
    protected function setElements(array $elements)
    {
        $this->_elements = $elements;
    }

    abstract protected function setKey($key);
}
