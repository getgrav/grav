<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
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
                $parent = $this->getPermissionsParent();
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
    public function isParentAuthorized(string $action, ?string $scope = null, ?UserInterface $user = null, bool $isAuthor = false): ?bool
    {
        $scope ??= $this->getAuthorizeScope();

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
     * The rules apply only to an enabled account and, for the session user, a completed login.
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
        // The page rules answer without calling authorize(), so they have to hold the same preconditions.
        // Account state is a fact about the account and applies to a user passed in explicitly as well.
        if ($user->get('state', 'enabled') !== 'enabled') {
            return null;
        }

        // As in authorize(), the session facts (logged in, past 2FA) apply to the session user only. Until
        // then the session user has no groups, is not an author and does not get the defaults either.
        if ($isMe && (!$user->authenticated || !$user->authorized)) {
            return null;
        }

        $authorized = null;

        // In admin we want to check against group permissions.
        $pageGroups = $this->getPermissions();
        // Group names are matched as strings, as an account may list a numeric name such as 2024 as an integer.
        $userGroups = array_map('strval', array_filter((array)$user->groups, static fn($name) => is_string($name) || is_int($name)));
        $config = Grav::instance()['config'];

        /** @var Access $access */
        foreach ($pageGroups as $group => $access) {
            // PHP turns a numeric group name into an integer array key, even if it was quoted in the page header.
            $group = (string)$group;
            if ($group === 'defaults') {
                // Special defaults permissions group applies to everyone who got this far, so not to guest.
            } elseif ($group === 'authors') {
                if (!$isAuthor) {
                    continue;
                }
            } elseif (!in_array($group, $userGroups, true)) {
                continue;
            } elseif (!$config->get("groups.{$group}.enabled", true)) {
                // A disabled group has no say, the same as UserGroupObject::authorize().
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
            $parent = $this->getPermissionsParent();
            if ($parent && method_exists($parent, 'isParentAuthorized')) {
                $authorized = $parent->isParentAuthorized($action, $scope, !$isMe ? $user : null, $isAuthor);
            }
        }

        return $authorized;
    }

    /**
     * Get the parent page which the permissions are inherited from.
     *
     * Permissions only exist on Flex pages, so the parent always comes from the Flex directory. Outside of admin
     * PageObject::parent() returns the parent from the page tree of the site instead, which is a regular page unless
     * system.pages.type is flex; a regular parent would silently end the inheritance and the page would fall back to
     * the account wide permissions.
     *
     * @return PageInterface|null
     */
    protected function getPermissionsParent(): ?PageInterface
    {
        return self::parent();
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
