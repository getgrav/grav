<?php

/**
 * @package    Grav\Framework\Compat
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Compat;

/**
 * Serializable trait
 *
 * Adds backwards compatibility to PHP 7.3 Serializable interface.
 *
 * Note: Remember to add: `implements \Serializable` to the classes which use this trait.
 *
 * @package Grav\Framework\Traits
 */
trait Serializable
{
    /**
     * @return string
     */
    final public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * @param string $serialized
     * @return void
     */
    final public function unserialize($serialized): void
    {
        $this->__unserialize(unserialize($serialized, ['allowed_classes' => $this->getUnserializeAllowedClasses()]));
    }

    /**
     * @return array|bool
     */
    protected function getUnserializeAllowedClasses()
    {
        return false;
    }
}
