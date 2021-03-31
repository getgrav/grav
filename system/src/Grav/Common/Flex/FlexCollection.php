<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex;

use Grav\Common\Flex\Traits\FlexCollectionTrait;
use Grav\Common\Flex\Traits\FlexGravTrait;

/**
 * Class FlexCollection
 *
 * @package Grav\Common\Flex
 * @template T of \Grav\Framework\Flex\Interfaces\FlexObjectInterface
 * @extends \Grav\Framework\Flex\FlexCollection<T>
 */
abstract class FlexCollection extends \Grav\Framework\Flex\FlexCollection
{
    use FlexGravTrait;
    use FlexCollectionTrait;
}
