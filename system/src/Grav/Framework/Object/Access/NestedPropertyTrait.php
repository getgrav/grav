<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Access;

use Grav\Framework\Object\Interfaces\ObjectInterface;
use RuntimeException;
use stdClass;
use function is_array;
use function is_object;

/**
 * Nested Property Object Trait
 * @package Grav\Framework\Object\Traits
 */
trait NestedPropertyTrait
{
    /**
     * @param string $property      Object property name.
     * @param string|null $separator     Separator, defaults to '.'
     * @return bool                 True if property has been defined (can be null).
     */
    public function hasNestedProperty($property, $separator = null)
    {
        $test = new stdClass;

        return $this->getNestedProperty($property, $test, $separator) !== $test;
    }

    /**
     * @param string $property      Object property to be fetched.
     * @param mixed|null $default    Default value if property has not been set.
     * @param string|null $separator Separator, defaults to '.'
     * @return mixed                Property value.
     */
    public function getNestedProperty($property, $default = null, $separator = null)
    {
        $separator = $separator ?: '.';
        $path = explode($separator, $property) ?: [];
        $offset = array_shift($path) ?? '';

        if (!$this->hasProperty($offset)) {
            return $default;
        }

        $current = $this->getProperty($offset);

        while ($path) {
            // Get property of nested Object.
            if ($current instanceof ObjectInterface) {
                if (method_exists($current, 'getNestedProperty')) {
                    return $current->getNestedProperty(implode($separator, $path), $default, $separator);
                }
                return $current->getProperty(implode($separator, $path), $default);
            }

            $offset = array_shift($path);

            if ((is_array($current) || is_a($current, 'ArrayAccess')) && isset($current[$offset])) {
                $current = $current[$offset];
            } elseif (is_object($current) && isset($current->{$offset})) {
                $current = $current->{$offset};
            } else {
                return $default;
            }
        };

        return $current;
    }


    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $value         New value.
     * @param string|null $separator     Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function setNestedProperty($property, $value, $separator = null)
    {
        $separator = $separator ?: '.';
        $path = explode($separator, $property) ?: [];
        $offset = array_shift($path) ?? '';

        if (!$path) {
            $this->setProperty($offset, $value);

            return $this;
        }

        $current = &$this->doGetProperty($offset, null, true);

        while ($path) {
            $offset = array_shift($path);

            // Handle arrays and scalars.
            if ($current === null) {
                $current = [$offset => []];
            } elseif (is_array($current)) {
                if (!isset($current[$offset])) {
                    $current[$offset] = [];
                }
            } else {
                throw new RuntimeException("Cannot set nested property {$property} on non-array value");
            }

            $current = &$current[$offset];
        };

        $current = $value;

        return $this;
    }

    /**
     * @param string $property      Object property to be updated.
     * @param string|null $separator     Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function unsetNestedProperty($property, $separator = null)
    {
        $separator = $separator ?: '.';
        $path = explode($separator, $property) ?: [];
        $offset = array_shift($path) ?? '';

        if (!$path) {
            $this->unsetProperty($offset);

            return $this;
        }

        $last = array_pop($path);
        $current = &$this->doGetProperty($offset, null, true);

        while ($path) {
            $offset = array_shift($path);

            // Handle arrays and scalars.
            if ($current === null) {
                return $this;
            }
            if (is_array($current)) {
                if (!isset($current[$offset])) {
                    return $this;
                }
            } else {
                throw new RuntimeException("Cannot unset nested property {$property} on non-array value");
            }

            $current = &$current[$offset];
        };

        unset($current[$last]);

        return $this;
    }

    /**
     * @param string $property      Object property to be updated.
     * @param mixed  $default       Default value.
     * @param string|null $separator     Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function defNestedProperty($property, $default, $separator = null)
    {
        if (!$this->hasNestedProperty($property, $separator)) {
            $this->setNestedProperty($property, $default, $separator);
        }

        return $this;
    }
}
