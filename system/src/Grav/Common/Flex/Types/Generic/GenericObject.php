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
use Grav\Common\Flex\Traits\FlexObjectTrait;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Traits\FlexMediaTrait;

/**
 * Class GenericObject
 * @package Grav\Common\Flex\Generic
 */
class GenericObject extends FlexObject
{
    use FlexGravTrait;
    use FlexObjectTrait;
    use FlexMediaTrait;
}
