<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Grav;
use Grav\Common\User\DataUser;
use Grav\Common\Flex;
use Grav\Common\User\Interfaces\UserCollectionInterface;
use Grav\Common\User\Interfaces\UserInterface;

if (!defined('GRAV_USER_INSTANCE')) {
    throw new \LogicException('User class was called too early!');
}

if (defined('GRAV_USER_INSTANCE') && GRAV_USER_INSTANCE === 'FLEX') {
    /**
     * @deprecated 1.6 Use $grav['accounts'] instead of static calls. In type hints, please use UserInterface.
     */
    class User extends Flex\Types\Users\UserObject
    {
        /**
         * Load user account.
         *
         * Always creates user object. To check if user exists, use $this->exists().
         *
         * @param string $username
         * @return UserInterface
         * @deprecated 1.6 Use $grav['accounts']->load(...) instead.
         */
        public static function load($username)
        {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use $grav[\'accounts\']->' . __FUNCTION__ . '() instead', E_USER_DEPRECATED);

            return static::getCollection()->load($username);
        }

        /**
         * Find a user by username, email, etc
         *
         * Always creates user object. To check if user exists, use $this->exists().
         *
         * @param string $query the query to search for
         * @param array $fields the fields to search
         * @return UserInterface
         * @deprecated 1.6 Use $grav['accounts']->find(...) instead.
         */
        public static function find($query, $fields = ['username', 'email'])
        {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use $grav[\'accounts\']->' . __FUNCTION__ . '() instead', E_USER_DEPRECATED);

            return static::getCollection()->find($query, $fields);
        }

        /**
         * Remove user account.
         *
         * @param string $username
         * @return bool True if the action was performed
         * @deprecated 1.6 Use $grav['accounts']->delete(...) instead.
         */
        public static function remove($username)
        {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use $grav[\'accounts\']->delete() instead', E_USER_DEPRECATED);

            return static::getCollection()->delete($username);
        }

        /**
         * @return UserCollectionInterface
         */
        protected static function getCollection()
        {
            return Grav::instance()['accounts'];
        }
    }
} else {
    /**
     * @deprecated 1.6 Use $grav['accounts'] instead of static calls. In type hints, use UserInterface.
     */
    class User extends DataUser\User
    {
        /**
         * Load user account.
         *
         * Always creates user object. To check if user exists, use $this->exists().
         *
         * @param string $username
         * @return UserInterface
         * @deprecated 1.6 Use $grav['accounts']->load(...) instead.
         */
        public static function load($username)
        {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use $grav[\'accounts\']->' . __FUNCTION__ . '() instead', E_USER_DEPRECATED);

            return static::getCollection()->load($username);
        }

        /**
         * Find a user by username, email, etc
         *
         * Always creates user object. To check if user exists, use $this->exists().
         *
         * @param string $query the query to search for
         * @param array $fields the fields to search
         * @return UserInterface
         * @deprecated 1.6 Use $grav['accounts']->find(...) instead.
         */
        public static function find($query, $fields = ['username', 'email'])
        {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use $grav[\'accounts\']->' . __FUNCTION__ . '() instead', E_USER_DEPRECATED);

            return static::getCollection()->find($query, $fields);
        }

        /**
         * Remove user account.
         *
         * @param string $username
         * @return bool True if the action was performed
         * @deprecated 1.6 Use $grav['accounts']->delete(...) instead.
         */
        public static function remove($username)
        {
            user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use $grav[\'accounts\']->delete() instead', E_USER_DEPRECATED);

            return static::getCollection()->delete($username);
        }

        /**
         * @return UserCollectionInterface
         */
        protected static function getCollection()
        {
            return Grav::instance()['accounts'];
        }
    }
}
