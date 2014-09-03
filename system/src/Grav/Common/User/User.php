<?php
namespace Grav\Common\User;

use Grav\Component\Data\Data;

/**
 * User object
 *
 * @author RocketTheme
 * @license MIT
 */
class User extends Data
{
    protected $password;

    /**
     * Authenticate user.
     *
     * If user password needs to be updated, new information will be saved.
     *
     * @param string $password  Plaintext password.
     * @return bool
     */
    public function authenticate($password)
    {
        $result = Authentication::verify($password, $this->password);

        // Password needs to be updated, save the file.
        if ($result == 2) {
            $this->password = Authentication::create($password);
            $this->save();
        }

        return (bool) $result;
    }

    /**
     * Checks user authorisation to the action.
     *
     * @param  string  $action
     * @return bool
     */
    public function authorise($action)
    {
        return $this->get("access.{$action}") === true;
    }
}
