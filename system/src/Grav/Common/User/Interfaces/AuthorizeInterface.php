<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\Interfaces;

/**
 * Interface AuthorizeInterface
 * @package Grav\Common\User\Interfaces
 */
interface AuthorizeInterface
{
    /**
     * Checks user authorization to the action.
     *
     * @param  string $action
     * @param  string|null $scope
     * @return bool|null
     */
    public function authorize(string $action, string $scope = null): ?bool;
}
