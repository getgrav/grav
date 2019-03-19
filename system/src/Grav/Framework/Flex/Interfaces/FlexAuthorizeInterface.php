<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Common\User\Interfaces\UserInterface;

/**
 * Defines authorization checks for Flex Objects.
 */
interface FlexAuthorizeInterface
{
    /**
     * Check if user is authorized to perform an action for the object.
     *
     * @param string $action            One of: `create`, `read`, `update`, `delete`, `save`, `list`
     * @param string|null $scope        One of: `admin`, `site`
     * @param UserInterface|null $user  Optional user. Defaults to the current user.
     *
     * @return bool Returns `true` if user is authorized to perform action, `false` otherwise.
     * @api
     */
    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null): bool;
}
