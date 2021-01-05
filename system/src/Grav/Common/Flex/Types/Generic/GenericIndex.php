<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Generic;

use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexIndexTrait;
use Grav\Framework\Flex\FlexIndex;

/**
 * Class GenericIndex
 * @package Grav\Common\Flex\Generic
 *
 * @extends FLexIndex<string,GenericObject,GenericCollection>
 * @mixin GenericCollection
 */
class GenericIndex extends FlexIndex
{
    use FlexGravTrait;
    use FlexIndexTrait;
}
