<?php
namespace Grav\Common\User;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\GravTrait;

/**
 * User object
 *
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

        // TODO: validate directory name
        $blueprints = new Blueprints('blueprints://user');
        $blueprint = $blueprints->get('account');
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
