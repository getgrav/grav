<?php
/**
 * @package    Grav.Common.User
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Grav;
use Grav\Common\User\Events\UserLoginEvent;
use RocketTheme\Toolbox\Event\Event;

abstract class Authentication
{
    /**
     * @param array $credentials
     * @param array $options
     * @return User|null
     */
    public static function login(array $credentials, array $options)
    {
        $grav = Grav::instance();

        $eventOptions = [
            'credentials' => $credentials,
            'options' => $options
        ];

        $event = new UserLoginEvent($eventOptions);

        // Attempt to authenticate the user.
        $grav->fireEvent('onUserLoginAuthenticate', $event);

        $event->removeCredentials();

        // Allow plugins to prevent login after successful authentication.
        if ($event['status'] === UserLoginEvent::AUTHENTICATION_SUCCESS) {
            $grav->fireEvent('onUserLoginAuthorize', $event);
        }

        // Allow plugins to log errors or do other tasks on failure.
        if ($event['status'] !== UserLoginEvent::AUTHENTICATION_SUCCESS) {
            $grav->fireEvent('onUserLoginFailure', $event);

            return null;
        }

        if (empty($event['user']->authenticated)) {
            throw new \RuntimeException('Login: User object has not been authenticated!');
        }

        // User has been logged in, let plugins know.
        $grav->fireEvent('onUserLogin', $event);

        return $event['user'];
    }

    public static function logout($user)
    {
        $grav = Grav::instance();

        $event = new Event;
        $event->user = $user;

        // Logout the user.
        $grav->fireEvent('onUserLogout', $event);
    }

    /**
     * Create password hash from plaintext password.
     *
     * @param string $password Plaintext password.
     *
     * @throws \RuntimeException
     * @return string|bool
     */
    public static function create($password)
    {
        if (!$password) {
            throw new \RuntimeException('Password hashing failed: no password provided.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        if (!$hash) {
            throw new \RuntimeException('Password hashing failed: internal error.');
        }

        return $hash;
    }

    /**
     * Verifies that a password matches a hash.
     *
     * @param string $password Plaintext password.
     * @param string $hash     Hash to verify against.
     *
     * @return int              Returns 0 if the check fails, 1 if password matches, 2 if hash needs to be updated.
     */
    public static function verify($password, $hash)
    {
        // Fail if hash doesn't match
        if (!$password || !$hash || !password_verify($password, $hash)) {
            return 0;
        }

        // Otherwise check if hash needs an update.
        return password_needs_rehash($hash, PASSWORD_DEFAULT) ? 2 : 1;
    }
}
