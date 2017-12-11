<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Interfaces;

/**
 * Object Interface
 * @package Grav\Framework\Object
 */
interface NestedObjectInterface extends ObjectInterface
{
    /**
     * @param string $property      Object property name.
     * @param string $separator     Separator, defaults to '.'
     * @return bool                 True if property has been defined (can be null).
     */
    public function hasNestedProperty($property, $separator = null);

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @param string $separator     Separator, defaults to '.'
     * @return mixed                Property value.
     */
    public function getNestedProperty($property, $default = null, $separator = null);

    /**
     * @param string $property      Object property to be updated.
     * @param string $value         New value.
     * @param string $separator     Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function setNestedProperty($property, $value, $separator = null);

    /**
     * @param string $property      Object property to be defined.
     * @param string $default       Default value.
     * @param string $separator     Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function defNestedProperty($property, $default, $separator = null);

    /**
     * @param string $property      Object property to be unset.
     * @param string $separator     Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function unsetNestedProperty($property, $separator = null);
}
