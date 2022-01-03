<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
     * Check if user is authorized for the action.
     *
     * Note: There are two deny values: denied (false), not set (null). This allows chaining multiple rules together
     * when the previous rules were not matched.
     *
     * @param string $action
     * @param string|null $scope
     * @param UserInterface|null $user
     * @return bool|null
     */
    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null): ?bool;
}
