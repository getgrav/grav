<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Interfaces;

/**
 * Object Interface
 * @package Grav\Framework\Object
 */
interface ObjectInterface extends \Serializable, \JsonSerializable
{
    /**
     * @param array $elements
     * @param string $key
     */
    public function __construct(array $elements = [], $key = null);

    /**
     * @return string
     */
    public function getType();

    /**
     * @return string
     */
    public function getKey();

    /**
     * @param string $property      Object property name.
     * @return bool                 True if property has been defined (can be null).
     */
    public function hasProperty($property);

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed $default        Default value if property has not been set.
     * @return mixed                Property value.
     */
    public function getProperty($property, $default = null);

    /**
     * @param string $property      Object property to be updated.
     * @param string $value         New value.
     * @return $this
     */
    public function setProperty($property, $value);

    /**
     * @param string  $property     Object property to be defined.
     * @param mixed   $default      Default value.
     * @return $this
     */
    public function defProperty($property, $default);

    /**
     * @param string  $property     Object property to be unset.
     * @return $this
     */
    public function unsetProperty($property);
}
