<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\Interfaces;

interface UserCollectionInterface extends \Countable
{
    /**
     * Load user account.
     *
     * Always creates user object. To check if user exists, use $this->exists().
     *
     * @param string $username
     * @return UserInterface
     */
    public function load($username): UserInterface;

    /**
     * Find a user by username, email, etc
     *
     * @param string $query the query to search for
     * @param array $fields the fields to search
     * @return UserInterface
     */
    public function find($query, $fields = ['username', 'email']): UserInterface;

    /**
     * Delete user account.
     *
     * @param string $username
     * @return bool True if user account was found and was deleted.
     */
    public function delete($username): bool;
}
