<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Access;

/**
 * ArrayAccess Object Trait
 * @package Grav\Framework\Object
 */
trait ArrayAccessTrait
{
    /**
     * Whether or not an offset exists.
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->hasProperty($offset);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->getProperty($offset);
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->setProperty($offset, $value);
    }

    /**
     * Unsets an offset.
     *
     * @param mixed $offset  The offset to unset.
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->unsetProperty($offset);
    }
}
