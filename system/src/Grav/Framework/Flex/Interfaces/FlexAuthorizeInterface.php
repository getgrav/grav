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
 * Interface FlexAuthorizeInterface
 * @package Grav\Framework\User\Interfaces
 */
interface FlexAuthorizeInterface
{
    /**
     * @param string $action        One of: create, read, update, delete, save, list
     * @param string|null $scope    One of: admin, site
     * @param UserInterface|null $user
     * @return bool
     */
    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null) : bool;
}
