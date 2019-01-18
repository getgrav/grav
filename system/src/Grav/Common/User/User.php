<?php
/**
 * @package    Grav.Common.User
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class User extends Data
{
    /**
     * Load user account.
     *
     * Always creates user object. To check if user exists, use $this->exists().
     *
     * @param string $username
     * @param bool $setConfig
     *
     * @return User
     */
    public static function load($username)
    {
        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        // force lowercase of username
        $username = mb_strtolower($username);

        $blueprints = new Blueprints;
        $blueprint = $blueprints->get('user/account');

        $filename = 'account://' . $username . YAML_EXT;
        $path = $locator->findResource($filename) ?: $locator->findResource($filename, true, true);
        $file = CompiledYamlFile::instance($path);
        $content = (array)$file->content() + ['username' => $username, 'state' => 'enabled'];

        $user = new static($content, $blueprint);
        $user->file($file);

        return $user;
    }

    /**
     * Find a user by username, email, etc
     *
     * @param string $query the query to search for
     * @param array $fields the fields to search
     * @return User
     */
    public static function find($query, $fields = ['username', 'email'])
    {
        $account_dir = Grav::instance()['locator']->findResource('account://');
        $files = $account_dir ? array_diff(scandir($account_dir), ['.', '..']) : [];

        // Try with username first, you never know!
        if (in_array('username', $fields, true)) {
            $user = static::load($query);
            unset($fields[array_search('username', $fields, true)]);
        } else {
            $user = static::load('');
        }

        // If not found, try the fields
        if (!$user->exists()) {
            foreach ($files as $file) {
                if (Utils::endsWith($file, YAML_EXT)) {
                    $find_user = static::load(trim(pathinfo($file, PATHINFO_FILENAME)));
                    foreach ($fields as $field) {
                        if ($find_user[$field] === $query) {
                            return $find_user;
                        }
                    }
                }
            }
        }
        return $user;
    }

    /**
     * Remove user account.
     *
     * @param string $username
     *
     * @return bool True if the action was performed
     */
    public static function remove($username)
    {
        $file_path = Grav::instance()['locator']->findResource('account://' . $username . YAML_EXT);

        return $file_path && unlink($file_path);
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $value = parent::offsetExists($offset);

        // Handle special case where user was logged in before 'authorized' was added to the user object.
        if (false === $value && $offset === 'authorized') {
            $value = $this->offsetExists('authenticated');
        }

        return $value;
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        $value = parent::offsetGet($offset);

        // Handle special case where user was logged in before 'authorized' was added to the user object.
        if (null === $value && $offset === 'authorized') {
            $value = $this->offsetGet('authenticated');
            $this->offsetSet($offset, $value);
        }

        return $value;
    }

    /**
     * Authenticate user.
     *
     * If user password needs to be updated, new information will be saved.
     *
     * @param string $password Plaintext password.
     *
     * @return bool
     */
    public function authenticate($password)
    {
        $save = false;

        // Plain-text is still stored
        $storedPassword = $this->get('password');
        if ($storedPassword) {
            if ($password !== $storedPassword) {
                // Plain-text passwords do not match, we know we should fail but execute
                // verify to protect us from timing attacks and return false regardless of
                // the result
                Authentication::verify(
                    $password,
                    Grav::instance()['config']->get('system.security.default_hash')
                );

                return false;
            }

            // Plain-text does match, we can update the hash and proceed
            $save = true;

            $this->set('hashed_password', Authentication::create($storedPassword));
            $this->undef('password');
        }

        $result = Authentication::verify($password, $this->get('hashed_password'));

        // Password needs to be updated, save the file.
        if ($result === 2) {
            $save = true;
            $this->set('hashed_password', Authentication::create($password));
        }

        if ($save) {
            $this->save();
        }

        return (bool)$result;
    }

    /**
     * Replace all data
     *
     * WARNING: There are no checks! All the data will be replaced.
     *
     * @param array $data
     * @return $this
     */
    public function update(array $data)
    {
        $this->items = $data;

        return $this;
    }

    /**
     * Save user without the username
     */
    public function save()
    {
        /** @var CompiledYamlFile $file */
        $file = $this->file();
        if (!$file || !$file->filename()) {
            user_error(__CLASS__ . ": calling \$user = new User() is deprecated since Grav 1.6, use User::load(\$username) or User::load('') instead", E_USER_DEPRECATED);
        }

        if ($file) {
            $username = $this->get('username');

            if (!$file->filename()) {
                $locator = Grav::instance()['locator'];
                $file->filename($locator->findResource('account://' . mb_strtolower($username) . YAML_EXT, true, true));
            }

            // if plain text password, hash it and remove plain text
            $password = $this->get('password');
            if ($password) {
                $this->set('hashed_password', Authentication::create($password));
                $this->undef('password');
            }

            $this->undef('username');
            $file->save($this->items);
            $this->set('username', $username);
        }
    }

    /**
     * Checks user authorization to the action.
     *
     * @param  string $action
     *
     * @return bool
     */
    public function authorize($action)
    {
        if (!$this->get('authenticated')) {
            return false;
        }

        if ($this->get('state', 'enabled') !== 'enabled') {
            return false;
        }

        $return = false;

        //Check group access level
        $groups = $this->get('groups');
        if ($groups) {
            foreach ((array)$groups as $group) {
                $permission = Grav::instance()['config']->get("groups.{$group}.access.{$action}");
                $return = Utils::isPositive($permission);
                if ($return === true) {
                    break;
                }
            }
        }

        //Check user access level
        $access = $this->get('access');
        if ($access && Utils::getDotNotation($access, $action) !== null) {
            $permission = $this->get("access.{$action}");
            $return = Utils::isPositive($permission);
        }

        return $return;
    }

    /**
     * Checks user authorization to the action.
     * Ensures backwards compatibility
     *
     * @param  string $action
     *
     * @deprecated use authorize()
     * @return bool
     */
    public function authorise($action)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use authorize() method instead', E_USER_DEPRECATED);

        return $this->authorize($action);
    }

    /**
     * Return the User's avatar URL
     *
     * @return string
     */
    public function avatarUrl()
    {
        $avatar = $this->get('avatar');
        if ($avatar) {
            $avatar = array_shift($avatar);

            $path = $avatar['path'] ?? null;
            if ($path) {
                return Grav::instance()['base_url'] . '/' . $path;
            }
        }

        $provider = $this->get('provider');
        if ($provider) {
            $avatar = $this->{$provider}['avatar_url'] ?? $this->{$provider}['avatar'] ?? null;
            if ($avatar) {
                return $avatar;
            }
        }

        return 'https://www.gravatar.com/avatar/' . md5($this->get('email'));
    }

    /**
     * Serialize user.
     */
    public function __sleep()
    {
        return [
            'items',
            'storage'
        ];
    }

    /**
     * Unserialize user.
     */
    public function __wakeup()
    {
        $this->gettersVariable = 'items';
        $this->nestedSeparator = '.';

        if (null === $this->blueprints) {
            $blueprints = new Blueprints;
            $this->blueprints = $blueprints->get('user/account');
        }
    }
}
