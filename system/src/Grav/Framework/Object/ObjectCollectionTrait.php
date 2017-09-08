<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * ObjectCollection Trait
 * @package Grav\Framework\Object
 */
trait ObjectCollectionTrait
{
    use ObjectTrait;

    /**
     * Create a copy from this collection by cloning all objects in the collection.
     *
     * @return static
     */
    public function copy()
    {
        $list = [];
        foreach ($this as $key => $value) {
            $list[$key] = is_object($value) ? clone $value : $value;
        }

        // TODO: remove when PHP 5.6 is minimum (with doctrine/collections v1.4).
        if (!method_exists($this, 'createFrom')) {
            return new static($list);
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
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if not set.
     * @return array                Key/Value pairs of the properties.
     */
    public function getProperty($property, $default = null)
    {
        $list = [];

        /** @var ObjectInterface $element */
        foreach ($this as $id => $element) {
            $list[$id] = $element->getProperty($property, $default);
        }

        return $list;
    }

    /**
     * @param string $property  Object property to be updated.
     * @param string $value     New value.
     * @return $this
     */
    public function setProperty($property, $value)
    {
        /** @var ObjectInterface $element */
        foreach ($this as $element) {
            $element->setProperty($property, $value);
        }

        return $this;
    }

    /**
     * @param string $method        Method name.
     * @param array  $arguments     List of arguments passed to the function.
     * @return array                Return values.
     */
    public function call($method, array $arguments = [])
    {
        $list = [];

        foreach ($this as $id => $element) {
            $list[$id] = method_exists($element, $method)
                ? call_user_func_array([$element, $method], $arguments) : null;
        }

        return $list;
    }


    /**
     * Group items in the collection by a field.
     *
     * @param string $property
     * @return array
     */
    public function group($property)
    {
        $list = [];

        /** @var ObjectInterface $element */
        foreach ($this as $element) {
            $list[(string) $element->getProperty($property)][] = $element;
        }

        return $list;
    }
}
