<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex;

use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexObjectTrait;
use Grav\Framework\Flex\Traits\FlexMediaTrait;

/**
 * Class FlexObject
 *
 * @package Grav\Common\Flex
 */
abstract class FlexObject extends \Grav\Framework\Flex\FlexObject
{
    use FlexGravTrait;
    use FlexObjectTrait;
    use FlexMediaTrait;
}
