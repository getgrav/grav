<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use ArrayAccess;
use Grav\Framework\Object\Access\NestedArrayAccessTrait;
use Grav\Framework\Object\Access\NestedPropertyTrait;
use Grav\Framework\Object\Access\OverloadedPropertyTrait;
use Grav\Framework\Object\Base\ObjectTrait;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Property\ArrayPropertyTrait;

/**
 * Array Objects keep the data in private array property.
 * @implements ArrayAccess<string,mixed>
 */
class ArrayObject implements NestedObjectInterface, ArrayAccess
{
    use ObjectTrait;
    use ArrayPropertyTrait;
    use NestedPropertyTrait;
    use OverloadedPropertyTrait;
    use NestedArrayAccessTrait;
}
