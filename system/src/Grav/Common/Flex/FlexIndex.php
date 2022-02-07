<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex;

use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexIndexTrait;

/**
 * Class FlexIndex
 *
 * @package Grav\Common\Flex
 * @template T of \Grav\Framework\Flex\Interfaces\FlexObjectInterface
 * @template C of \Grav\Framework\Flex\Interfaces\FlexCollectionInterface
 * @extends \Grav\Framework\Flex\FlexIndex<T,C>
 */
abstract class FlexIndex extends \Grav\Framework\Flex\FlexIndex
{
    use FlexGravTrait;
    use FlexIndexTrait;
}
