<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Interfaces;

/**
 * Common Interface for both Objects and Collections
 * @package Grav\Framework\Object
 */
interface NestedObjectInterface extends ObjectInterface
{
    /**
     * @param  string       $property   Object property name.
     * @param  string|null  $separator  Separator, defaults to '.'
     * @return bool|bool[]              True if property has been defined (can be null).
     */
    public function hasNestedProperty($property, $separator = null);

    /**
     * @param  string       $property   Object property to be fetched.
     * @param  mixed|null   $default    Default value if property has not been set.
     * @param  string|null  $separator  Separator, defaults to '.'
     * @return mixed|mixed[]            Property value.
     */
    public function getNestedProperty($property, $default = null, $separator = null);

    /**
     * @param  string       $property    Object property to be updated.
     * @param  mixed        $value       New value.
     * @param  string|null  $separator   Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function setNestedProperty($property, $value, $separator = null);

    /**
     * @param  string $property         Object property to be defined.
     * @param  mixed  $default          Default value.
     * @param  string|null $separator   Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function defNestedProperty($property, $default, $separator = null);

    /**
     * @param  string       $property   Object property to be unset.
     * @param  string|null  $separator  Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function unsetNestedProperty($property, $separator = null);
}
