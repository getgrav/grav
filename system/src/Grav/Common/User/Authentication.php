<?php
namespace Grav\Common\User;

/**
 * User authentication
 *
 * @author RocketTheme
 * @license MIT
 */
abstract class Authentication
{
    /**
     * Create password hash from plaintext password.
     *
     * @param string $password  Plaintext password.
     * @return string|bool
     */
    public static function create($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifies that a password matches a hash.
     *
     * @param string $password  Plaintext password.
     * @param string $hash      Hash to verify against.
     * @return int              Returns 0 if the check fails, 1 if password matches, 2 if hash needs to be updated.
     */
    public static function verify($password, $hash)
    {
        // Always accept plaintext passwords (needs an update).
        if ($password && $password == $hash) {
            return 2;
        }

        // Fail if hash doesn't match.
        if (!$password || !password_verify($password, $hash)) {
            return 0;
        }

        // Otherwise check if hash needs an update.
        return password_needs_rehash($hash, PASSWORD_DEFAULT) ? 2 : 1;
    }
}
