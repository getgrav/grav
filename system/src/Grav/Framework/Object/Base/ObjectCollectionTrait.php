<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Base;

use Grav\Framework\Object\Interfaces\ObjectInterface;

/**
 * ObjectCollection Trait
 * @package Grav\Framework\Object
 */
trait ObjectCollectionTrait
{
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
     * @return bool
     */
    public function hasKey()
    {
        return !empty($this->_key);
    }

    /**
     * @param string $property      Object property name.
     * @return bool[]               True if property has been defined (can be null).
     */
    public function hasProperty($property)
    {
        return $this->doHasProperty($property);
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @return mixed[]              Property values.
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
     * Implements Serializable interface.
     *
     * @return string
     */
    public function serialize()
    {
        return serialize($this->doSerialize());
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

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
     */
    protected function doUnserialize(array $serialized)
    {
        if (!isset($serialized['key'], $serialized['type'], $serialized['elements']) || $serialized['type'] !== $this->getType()) {
            throw new \InvalidArgumentException("Cannot unserialize '{$this->getType()}': Bad data");
        }

        $this->setKey($serialized['key']);
        $this->setElements($serialized['elements']);
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->doSerialize();
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getKey();
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->_key = (string) $key;

        return $this;
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
        return $this->call('getKey');
    }

    /**
     * @param string $property      Object property to be matched.
     * @return bool[]               Key/Value pairs of the properties.
     */
    public function doHasProperty($property)
    {
        $list = [];

        /** @var ObjectInterface $element */
        foreach ($this->getIterator() as $id => $element) {
            $list[$id] = (bool)$element->hasProperty($property);
        }

        return $list;
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if not set.
     * @return mixed[]              Key/Value pairs of the properties.
     */
    public function doGetProperty($property, $default = null)
    {
        $list = [];

        /** @var ObjectInterface $element */
        foreach ($this->getIterator() as $id => $element) {
            $list[$id] = $element->getProperty($property, $default);
        }

        return $list;
    }

    /**
     * @param string $property  Object property to be updated.
     * @param mixed  $value     New value.
     * @return $this
     */
    public function doSetProperty($property, $value)
    {
        /** @var ObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $element->setProperty($property, $value);
        }

        return $this;
    }

    /**
     * @param string $property  Object property to be updated.
     * @return $this
     */
    public function doUnsetProperty($property)
    {
        /** @var ObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $element->unsetProperty($property);
        }

        return $this;
    }

    /**
     * @param string $property  Object property to be updated.
     * @param mixed  $default   Default value.
     * @return $this
     */
    public function doDefProperty($property, $default)
    {
        /** @var ObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $element->defProperty($property, $default);
        }

        return $this;
    }

    /**
     * @param string $method        Method name.
     * @param array  $arguments     List of arguments passed to the function.
     * @return mixed[]              Return values.
     */
    public function call($method, array $arguments = [])
    {
        $list = [];

        /**
         * @var string|int $id
         * @var ObjectInterface $element
         */
        foreach ($this->getIterator() as $id => $element) {
            $callable = [$element, $method];
            $list[$id] = is_callable($callable) ? \call_user_func_array($callable, $arguments) : null;
        }

        return $list;
    }

    /**
     * Group items in the collection by a field and return them as associated array.
     *
     * @param string $property
     * @return array
     */
    public function group($property)
    {
        $list = [];

        /** @var ObjectInterface $element */
        foreach ($this->getIterator() as $element) {
            $list[(string) $element->getProperty($property)][] = $element;
        }

        return $list;
    }

    /**
     * Group items in the collection by a field and return them as associated array of collections.
     *
     * @param string $property
     * @return static[]
     */
    public function collectionGroup($property)
    {
        $collections = [];
        foreach ($this->group($property) as $id => $elements) {
            $collection = $this->createFrom($elements);

            $collections[$id] = $collection;
        }

        return $collections;
    }

    /**
     * @return \Traversable
     */
    abstract public function getIterator();

    abstract protected function getElements();
    abstract protected function setElements(array $elements);
}
