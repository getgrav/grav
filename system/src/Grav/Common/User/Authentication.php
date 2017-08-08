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

abstract class Authentication
{
    /**
     * Login user.
     *
     * @param array $credentials
     * @param array $options
     * @return User
     */
    public static function login(array $credentials, array $options = [])
    {
        $grav = Grav::instance();

        $eventOptions = [
            'credentials' => $credentials,
            'options' => $options
        ];

        // Attempt to authenticate the user.
        $event = new UserLoginEvent($eventOptions);
        $grav->fireEvent('onUserLoginAuthenticate', $event);

        // Allow plugins to prevent login after successful authentication.
        if ($event->status === UserLoginEvent::AUTHENTICATION_SUCCESS) {
            $event = new UserLoginEvent($event->toArray());
            $grav->fireEvent('onUserLoginAuthorize', $event);
        }

        if ($event->status !== UserLoginEvent::AUTHENTICATION_SUCCESS) {
            // Allow plugins to log errors or do other tasks on failure.
            $event = new UserLoginEvent($event->toArray());
            $grav->fireEvent('onUserLoginFailure', $event);

            $event->user->authenticated = false;

        } else {
            // User has been logged in, let plugins know.
            $event = new UserLoginEvent($event->toArray());
            $grav->fireEvent('onUserLogin', $event);

            $event->user->authenticated = true;
        }

        return $event->user;
    }

    /**
     * Logout user.
     *
     * @param User $user
     * @param array $options
     * @return User
     */
    public static function logout(User $user, array $options = [])
    {
        $grav = Grav::instance();

        $eventOptions = [
            'user' => $user,
            'options' => $options
        ];

        $event = new UserLoginEvent($eventOptions);

        // Logout the user.
        $grav->fireEvent('onUserLogout', $event);

        $event->user->authenticated = false;

        return $event->user;
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
