<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\DataUser;

use Grav\Common\Data\Blueprints;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserCollectionInterface;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function count;
use function in_array;
use function is_string;

/**
 * Class UserCollection
 * @package Grav\Common\User\DataUser
 */
class UserCollection implements UserCollectionInterface
{
    /** @var string */
    private $className;

    /**
     * UserCollection constructor.
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * Load user account.
     *
     * Always creates user object. To check if user exists, use $this->exists().
     *
     * @param string $username
     * @return UserInterface
     */
    public function load($username): UserInterface
    {
        $username = (string)$username;

        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        // Filter username.
        $username = $this->filterUsername($username);

        $filename = 'account://' . $username . YAML_EXT;
        $path = $locator->findResource($filename) ?: $locator->findResource($filename, true, true);
        if (!is_string($path)) {
            throw new RuntimeException('Internal Error');
        }
        $file = CompiledYamlFile::instance($path);
        $content = (array)$file->content() + ['username' => $username, 'state' => 'enabled'];

        $userClass = $this->className;
        $callable = static function () {
            $blueprints = new Blueprints;

            return $blueprints->get('user/account');
        };

        /** @var UserInterface $user */
        $user = new $userClass($content, $callable);
        $user->file($file);

        return $user;
    }

    /**
     * Find a user by username, email, etc
     *
     * @param string $query the query to search for
     * @param array $fields the fields to search
     * @return UserInterface
     */
    public function find($query, $fields = ['username', 'email']): UserInterface
    {
        $fields = (array)$fields;

        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $account_dir = $locator->findResource('account://');
        if (!is_string($account_dir)) {
            return $this->load('');
        }

        $files = array_diff(scandir($account_dir) ?: [], ['.', '..']);

        // Try with username first, you never know!
        if (in_array('username', $fields, true)) {
            $user = $this->load($query);
            unset($fields[array_search('username', $fields, true)]);
        } else {
            $user = $this->load('');
        }

        // If not found, try the fields
        if (!$user->exists()) {
            foreach ($files as $file) {
                if (Utils::endsWith($file, YAML_EXT)) {
                    $find_user = $this->load(trim(pathinfo($file, PATHINFO_FILENAME)));
                    foreach ($fields as $field) {
                        if (isset($find_user[$field]) && $find_user[$field] === $query) {
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
     * @return bool True if the action was performed
     */
    public function delete($username): bool
    {
        $file_path = Grav::instance()['locator']->findResource('account://' . $username . YAML_EXT);

        return $file_path && unlink($file_path);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        // check for existence of a user account
        $account_dir = $file_path = Grav::instance()['locator']->findResource('account://');
        $accounts = glob($account_dir . '/*.yaml') ?: [];

        return count($accounts);
    }

    /**
     * @param string $username
     * @return string
     */
    protected function filterUsername(string $username): string
    {
        return mb_strtolower($username);
    }
}
