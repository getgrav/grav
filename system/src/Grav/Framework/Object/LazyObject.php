<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use Grav\Framework\Object\Access\NestedArrayAccessTrait;
use Grav\Framework\Object\Access\NestedPropertyTrait;
use Grav\Framework\Object\Access\OverloadedPropertyTrait;
use Grav\Framework\Object\Base\ObjectTrait;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Property\LazyPropertyTrait;

/**
 * Lazy Objects keep their data in both protected object properties and falls back to a stored array if property does
 * not exist or is not initialized.
 *
 * @package Grav\Framework\Object
 */
class LazyObject implements NestedObjectInterface, \ArrayAccess
{
    use ObjectTrait;
    use LazyPropertyTrait;
    use NestedPropertyTrait;
    use OverloadedPropertyTrait;
    use NestedArrayAccessTrait;
}
