<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\UserGroups;

use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexIndexTrait;
use Grav\Framework\Flex\FlexIndex;

/**
 * Class GroupIndex
 * @package Grav\Common\User\FlexUser
 *
 * @method bool|null authorize(string $action, string $scope = null)
 */
class UserGroupIndex extends FlexIndex
{
    use FlexGravTrait;
    use FlexIndexTrait;
}
