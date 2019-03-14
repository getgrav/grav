<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use Doctrine\Common\Collections\Criteria;
use Grav\Framework\Collection\AbstractIndexCollection;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;

/**
 * Keeps index of objects instead of collection of objects. This class allows you to keep a list of objects and load
 * them on demand. The class can be used seemingly instead of ObjectCollection when the objects haven't been loaded yet.
 *
 * This is an abstract class and has some protected abstract methods to load objects which you need to implement in
 * order to use the class.
 */
abstract class ObjectIndex extends AbstractIndexCollection implements ObjectCollectionInterface, NestedObjectInterface
{
    /** @var string */
    static protected $type;

    /**
     * @var string
     */
    private $_key;

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

        $class = \get_class($this);
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
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->_key = $key;

        return $this;
    }

    /**
     * @param string $property      Object property name.
     * @return array                True if property has been defined (can be null).
     */
    public function hasProperty($property)
    {
        return $this->__call('hasProperty', [$property]);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @return array                Property values.
     */
    public function getProperty($property, $default = null)
    {
        return $this->__call('getProperty', [$property, $default]);
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string $value         New value.
     * @return ObjectCollectionInterface
     */
    public function setProperty($property, $value)
    {
        return $this->__call('setProperty', [$property, $value]);
    }

    /**
     * @param string  $property     Object property to be defined.
     * @param mixed   $default      Default value.
     * @return ObjectCollectionInterface
     */
    public function defProperty($property, $default)
    {
        return $this->__call('defProperty', [$property, $default]);
    }

    /**
     * @param string  $property     Object property to be unset.
     * @return ObjectCollectionInterface
     */
    public function unsetProperty($property)
    {
        return $this->__call('unsetProperty', [$property]);
    }

    /**
     * @param string $property      Object property name.
     * @param string $separator     Separator, defaults to '.'
     * @return bool                 True if property has been defined (can be null).
     */
    public function hasNestedProperty($property, $separator = null)
    {
        return $this->__call('hasNestedProperty', [$property, $separator]);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed  $default       Default value if property has not been set.
     * @param string $separator     Separator, defaults to '.'
     * @return mixed                Property value.
     */
    public function getNestedProperty($property, $default = null, $separator = null)
    {
        return $this->__call('getNestedProperty', [$property, $default, $separator]);
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string $value         New value.
     * @param string $separator     Separator, defaults to '.'
     * @return ObjectCollectionInterface
     */
    public function setNestedProperty($property, $value, $separator = null)
    {
        return $this->__call('setNestedProperty', [$property, $value, $separator]);
    }

    /**
     * @param string  $property     Object property to be defined.
     * @param mixed   $default      Default value.
     * @param string  $separator    Separator, defaults to '.'
     * @return ObjectCollectionInterface
     */
    public function defNestedProperty($property, $default, $separator = null)
    {
        return $this->__call('defNestedProperty', [$property, $default, $separator]);
    }

    /**
     * @param string  $property     Object property to be unset.
     * @return ObjectCollectionInterface
     */
    public function unsetNestedProperty($property, $separator = null)
    {
        return $this->__call('unsetNestedProperty', [$property, $separator]);
    }

    /**
     * Create a copy from this collection by cloning all objects in the collection.
     *
     * @return static
     */
    public function copy()
    {
        $list = [];
        foreach ($this->getIterator() as $key => $value) {
            $list[$key] = \is_object($value) ? clone $value : $value;
        }

        return $this->createFrom($list);
    }

    /**
     * @return array
     */
    public function getObjectKeys()
    {
        return $this->getKeys();
    }

    /**
     * @param array $ordering
     * @return ObjectCollectionInterface
     */
    public function orderBy(array $ordering)
    {
        return $this->__call('orderBy', [$ordering]);
    }

    /**
     * {@inheritDoc}
     */
    public function call($method, array $arguments = [])
    {
        return $this->__call('call', [$method, $arguments]);
    }

    /**
     * Group items in the collection by a field and return them as associated array.
     *
     * @param string $property
     * @return array
     */
    public function group($property)
    {
        return $this->__call('group', [$property]);
    }

    /**
     * Group items in the collection by a field and return them as associated array of collections.
     *
     * @param string $property
     * @return ObjectCollectionInterface[]
     */
    public function collectionGroup($property)
    {
        return $this->__call('collectionGroup', [$property]);
    }

    /**
     * {@inheritDoc}
     */
    public function matching(Criteria $criteria)
    {
        /** @var ObjectCollectionInterface $collection */
        $collection = $this->loadCollection($this->getEntries());

        return $collection->matching($criteria);
    }

    abstract public function __call($name, $arguments);

    /**
     * @return string
     */
    protected function getTypePrefix()
    {
        return '';
    }
}
