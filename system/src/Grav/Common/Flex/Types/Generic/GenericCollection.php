<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Generic;

use Grav\Common\Flex\Traits\FlexCollectionTrait;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Framework\Flex\FlexCollection;

/**
 * Class GenericCollection
 * @package Grav\Common\Flex\Generic
 *
 * @extends FLexCollection<string,GenericObject>
 */
class GenericCollection extends FlexCollection
{
    use FlexGravTrait;
    use FlexCollectionTrait;
}
