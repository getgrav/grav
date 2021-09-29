<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Interfaces;

use Doctrine\Common\Collections\Selectable;
use Grav\Framework\Collection\CollectionInterface;
use Serializable;

/**
 * ObjectCollection Interface
 * @package Grav\Framework\Collection
 * @template TKey of array-key
 * @template T
 * @extends CollectionInterface<TKey,T>
 * @extends Selectable<TKey,T>
 */
interface ObjectCollectionInterface extends CollectionInterface, Selectable, Serializable
{
    /**
     * @return string
     */
    public function getType();

    /**
     * @return string
     */
    public function getKey();

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key);

    /**
     * @param  string       $property   Object property name.
     * @return bool[]                   List of [key => bool] pairs.
     */
    public function hasProperty($property);

    /**
     * @param  string       $property   Object property to be fetched.
     * @param  mixed|null   $default    Default value if property has not been set.
     * @return mixed[]                  List of [key => value] pairs.
     */
    public function getProperty($property, $default = null);

    /**
     * @param  string   $property      Object property to be updated.
     * @param  mixed    $value         New value.
     * @return $this
     */
    public function setProperty($property, $value);

    /**
     * @param  string  $property        Object property to be defined.
     * @param  mixed   $default         Default value.
     * @return $this
     */
    public function defProperty($property, $default);

    /**
     * @param  string  $property     Object property to be unset.
     * @return $this
     */
    public function unsetProperty($property);

    /**
     * Create a copy from this collection by cloning all objects in the collection.
     *
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function copy();

    /**
     * @return array
     */
    public function getObjectKeys();

    /**
     * @param string $name          Method name.
     * @param array  $arguments     List of arguments passed to the function.
     * @return array                Return values.
     */
    public function call($name, array $arguments = []);

    /**
     * Group items in the collection by a field and return them as associated array.
     *
     * @param string $property
     * @return array
     */
    public function group($property);

    /**
     * Group items in the collection by a field and return them as associated array of collections.
     *
     * @param string $property
     * @return static[]
     * @phpstan-return array<static<TKey,T>>
     */
    public function collectionGroup($property);

    /**
     * @param array $ordering
     * @return ObjectCollectionInterface
     * @phpstan-return static<TKey,T>
     */
    public function orderBy(array $ordering);

    /**
     * @param int $start
     * @param int|null $limit
     * @return ObjectCollectionInterface
     * @phpstan-return static<TKey,T>
     */
    public function limit($start, $limit = null);
}
