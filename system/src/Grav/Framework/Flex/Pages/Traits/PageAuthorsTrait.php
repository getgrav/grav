<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Acl\Access;
use InvalidArgumentException;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Trait PageAuthorsTrait
 * @package Grav\Framework\Flex\Pages\Traits
 */
trait PageAuthorsTrait
{
    /** @var array<int,UserInterface> */
    private $_authors;
    /** @var array|null */
    private $_permissionsCache;

    /**
     * Returns true if object has the named author.
     *
     * @param string $username
     * @return bool
     */
    public function hasAuthor(string $username): bool
    {
        $authors = (array)$this->getNestedProperty('header.permissions.authors');
        if (empty($authors)) {
            return false;
        }

        foreach ($authors as $author) {
            if ($username === $author) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of all author objects.
     *
     * @return array<int,UserInterface>
     */
    public function getAuthors(): array
    {
        if (null === $this->_authors) {
            $this->_authors = $this->loadAuthors($this->getNestedProperty('header.permissions.authors', []));
        }

        return $this->_authors;
    }

    /**
     * @param bool $inherit
     * @return array
     */
    public function getPermissions(bool $inherit = false)
    {
        if (null === $this->_permissionsCache) {
            $permissions = [];
            if ($inherit && $this->getNestedProperty('header.permissions.inherit', true)) {
                $parent = $this->parent();
                if ($parent && method_exists($parent, 'getPermissions')) {
                    $permissions = $parent->getPermissions($inherit);
                }
            }

            $this->_permissionsCache = $this->loadPermissions($permissions);
        }

        return $this->_permissionsCache;
    }

    /**
     * @param iterable $authors
     * @return array<int,UserInterface>
     */
    protected function loadAuthors(iterable $authors): array
    {
        $accounts = $this->loadAccounts();
        if (null === $accounts || empty($authors)) {
            return [];
        }

        $list = [];
        foreach ($authors as $username) {
            if (!is_string($username)) {
                throw new InvalidArgumentException('Iterable should return username (string).', 500);
            }
            $list[] = $accounts->load($username);
        }

        return $list;
    }

    /**
     * @param string $action
     * @param string|null $scope
     * @param UserInterface|null $user
     * @param bool $isAuthor
     * @return bool|null
     */
    public function isParentAuthorized(string $action, string $scope = null, UserInterface $user = null, bool $isAuthor = false): ?bool
    {
        $scope = $scope ?? $this->getAuthorizeScope();

        $isMe = null === $user;
        if ($isMe) {
            $user = $this->getActiveUser();
        }

        if (null === $user) {
            return false;
        }

        return $this->isAuthorizedByGroup($user, $action, $scope, $isMe, $isAuthor);
    }

    /**
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @param bool $isMe
     * @return bool|null
     */
    protected function isAuthorizedOverride(UserInterface $user, string $action, string $scope, bool $isMe): ?bool
    {
        if ($action === 'delete' && $this->root()) {
            // Do not allow deleting root.
            return false;
        }

        $isAuthor = !$isMe || $user->authorized ? $this->hasAuthor($user->username) : false;

        return $this->isAuthorizedByGroup($user, $action, $scope, $isMe, $isAuthor) ?? parent::isAuthorizedOverride($user, $action, $scope, $isMe);
    }

    /**
     * Group authorization works as follows:
     *
     * 1. if any of the groups deny access, return false
     * 2. else if any of the groups allow access, return true
     * 3. else return null
     *
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @param bool $isMe
     * @param bool $isAuthor
     * @return bool|null
     */
    protected function isAuthorizedByGroup(UserInterface $user, string $action, string $scope, bool $isMe, bool $isAuthor): ?bool
    {
        $authorized = null;

        // In admin we want to check against group permissions.
        $pageGroups = $this->getPermissions();
        $userGroups = (array)$user->groups;

        /** @var Access $access */
        foreach ($pageGroups as $group => $access) {
            if ($group === 'defaults') {
                // Special defaults permissions group does not apply to guest.
                if ($isMe && !$user->authorized) {
                    continue;
                }
            } elseif ($group === 'authors') {
                if (!$isAuthor) {
                    continue;
                }
            } elseif (!in_array($group, $userGroups, true)) {
                continue;
            }

            $auth = $access->authorize($action);
            if (is_bool($auth)) {
                if ($auth === false) {
                    return false;
                }

                $authorized = true;
            }
        }

        if (null === $authorized && $this->getNestedProperty('header.permissions.inherit', true)) {
            // Authorize against parent page.
            $parent = $this->parent();
            if ($parent && method_exists($parent, 'isParentAuthorized')) {
                $authorized = $parent->isParentAuthorized($action, $scope, !$isMe ? $user : null, $isAuthor);
            }
        }

        return $authorized;
    }

    /**
     * @param array $parent
     * @return array
     */
    protected function loadPermissions(array $parent = []): array
    {
        static $rules = [
            'c' => 'create',
            'r' => 'read',
            'u' => 'update',
            'd' => 'delete',
            'p' => 'publish',
            'l' => 'list'
        ];

        $permissions = $this->getNestedProperty('header.permissions.groups');
        $name = $this->root() ? '<root>' : '/' . $this->getKey();

        $list = [];
        if (is_array($permissions)) {
            foreach ($permissions as $group => $access) {
                $list[$group] = new Access($access, $rules, $name);
            }
        }
        foreach ($parent as $group => $access) {
            if (isset($list[$group])) {
                $object = $list[$group];
            } else {
                $object = new Access([], $rules, $name);
                $list[$group] = $object;
            }

            $object->inherit($access);
        }

        return $list;
    }
}
