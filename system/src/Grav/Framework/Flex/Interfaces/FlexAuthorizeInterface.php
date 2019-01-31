<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

/**
 * Interface FlexAuthorizeInterface
 * @package Grav\Framework\User\Interfaces
 */
interface FlexAuthorizeInterface
{
    /**
     * @param string $action        One of: create, read, update, delete, save, list
     * @param string|null $scope    One of: admin, site
     * @return bool
     */
    public function authorize(string $action, string $scope = null) : bool;
}
