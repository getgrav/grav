<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Interfaces;

use RuntimeException;

/**
 * Common Interface for both Objects and Collections
 * @package Grav\Framework\Object
 *
 * @template TKey of array-key
 * @template T
 * @extends ObjectCollectionInterface<TKey,T>
 */
interface NestedObjectCollectionInterface extends ObjectCollectionInterface
{
    /**
     * @param  string       $property   Object property name.
     * @param  string|null  $separator  Separator, defaults to '.'
     * @return bool[]                   List of [key => bool] pairs.
     */
    public function hasNestedProperty($property, $separator = null);

    /**
     * @param  string       $property   Object property to be fetched.
     * @param  mixed|null   $default    Default value if property has not been set.
     * @param  string|null  $separator  Separator, defaults to '.'
     * @return mixed[]                  List of [key => value] pairs.
     */
    public function getNestedProperty($property, $default = null, $separator = null);

    /**
     * @param  string       $property    Object property to be updated.
     * @param  mixed        $value       New value.
     * @param  string|null  $separator   Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function setNestedProperty($property, $value, $separator = null);

    /**
     * @param  string $property         Object property to be defined.
     * @param  mixed  $default          Default value.
     * @param  string|null $separator   Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function defNestedProperty($property, $default, $separator = null);

    /**
     * @param  string       $property   Object property to be unset.
     * @param  string|null  $separator  Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function unsetNestedProperty($property, $separator = null);
}
