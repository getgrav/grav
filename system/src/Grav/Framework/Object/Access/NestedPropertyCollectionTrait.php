<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Access;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;

/**
 * Nested Properties Collection Trait
 * @package Grav\Framework\Object\Properties
 */
trait NestedPropertyCollectionTrait
{
    /**
     * @param string $property      Object property to be matched.
     * @param string $separator     Separator, defaults to '.'
     * @return array                Key/Value pairs of the properties.
     */
    public function hasNestedProperty($property, $separator = null)
    {
        $list = [];

        /** @var NestedObjectInterface $element */
        foreach ($this->getIterator() as $id => $element) {
            $list[$id] = $element->hasNestedProperty($property, $separator);
        }

        return $list;
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if not set.
     * @param string $separator     Separator, defaults to '.'
     * @return array                Key/Value pairs of the properties.
     */
    public function getNestedProperty($property, $default = null, $separator = null)
    {
        $list = [];

        /** @var NestedObjectInterface $element */
        foreach ($this->getIterator() as $id => $element) {
            $list[$id] = $element->getNestedProperty($property, $default, $separator);
        }

        return $list;
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string $value         New value.
     * @param string $separator     Separator, defaults to '.'
     * @return $this
     */
    public function setNestedProperty($property, $value, $separator = null)
    {
        /** @var NestedObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $element->setNestedProperty($property, $value, $separator);
        }

        return $this;
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string $separator     Separator, defaults to '.'
     * @return $this
     */
    public function unsetNestedProperty($property, $separator = null)
    {
        /** @var NestedObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $element->unsetNestedProperty($property, $separator);
        }

        return $this;
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string $default       Default value.
     * @param string $separator     Separator, defaults to '.'
     * @return $this
     */
    public function defNestedProperty($property, $default, $separator = null)
    {
        /** @var NestedObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $element->defNestedProperty($property, $default, $separator);
        }

        return $this;
    }

    /**
     * Group items in the collection by a field.
     *
     * @param string $property      Object property to be used to make groups.
     * @param string $separator     Separator, defaults to '.'
     * @return array
     */
    public function group($property, $separator = null)
    {
        $list = [];

        /** @var NestedObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $list[(string) $element->getNestedProperty($property, null, $separator)][] = $element;
        }

        return $list;
    }

    /**
     * @return \Traversable
     */
    abstract public function getIterator();
}
