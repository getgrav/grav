<?php
namespace Grav\Common\User;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\GravTrait;

/**
 * User object
 *
 * @property mixed authenticated
 * @property mixed password
 * @property bool|string hashed_password
 * @author RocketTheme
 * @license MIT
 */
class User extends Data
{
    use GravTrait;

    /**
     * Load user account.
     *
     * Always creates user object. To check if user exists, use $this->exists().
     *
     * @param string $username
     * @return User
     */
    public static function load($username)
    {
        $locator = self::getGrav()['locator'];

        $blueprints = new Blueprints('blueprints://');
        $blueprint = $blueprints->get('user/account');
        $file_path = $locator->findResource('account://' . $username . YAML_EXT);
        $file = CompiledYamlFile::instance($file_path);
        $content = $file->content();
        if (!isset($content['username'])) {
            $content['username'] = $username;
        }
        $user = new User($content, $blueprint);
        $user->file($file);

        return $user;
    }

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
        $save = false;

        // Plain-text is still stored
        if ($this->password) {
            if ($password !== $this->password) {
                // Plain-text passwords do not match, we know we should fail but execute
                // verify to protect us from timing attacks and return false regardless of
                // the result
                Authentication::verify($password, self::getGrav()['config']->get('system.security.default_hash'));
                return false;
            } else {
                // Plain-text does match, we can update the hash and proceed
                $save = true;

                $this->hashed_password = Authentication::create($this->password);
                unset($this->password);
            }

        }

        $result = Authentication::verify($password, $this->hashed_password);

        // Password needs to be updated, save the file.
        if ($result == 2) {
            $save = true;
            $this->hashed_password = Authentication::create($password);
        }

        if ($save) {
            $this->save();
        }

        return (bool) $result;
    }

    /**
     * Save user without the username
     */
    public function save()
    {
        $file = $this->file();
        if ($file) {
            // if plain text password, hash it and remove plain text
            if ($this->password) {
                $this->hashed_password = Authentication::create($this->password);
                unset($this->password);
            }

            $username = $this->get('username');
            unset($this->username);
            $file->save($this->items);
            $this->set('username', $username);
        }
    }

    /**
     * Checks user authorization to the action.
     *
     * @param  string  $action
     * @return bool
     */
    public function authorize($action)
    {
        if (empty($this->items)) {
            return false;
        }

        return $this->get("access.{$action}") === true;
    }

    /**
     * Checks user authorization to the action.
     * Ensures backwards compatibility
     *
     * @param  string $action
     * @deprecated use authorize()
     * @return bool
     */
    public function authorise($action)
    {
        return $this->authorize($action);
    }
}
