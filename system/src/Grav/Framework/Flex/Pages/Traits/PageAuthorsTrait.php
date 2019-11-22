<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Grav\Common\User\Access;
use Grav\Common\User\Interfaces\UserCollectionInterface;
use Grav\Common\User\Interfaces\UserInterface;

/**
 * Implements PageAuthorsTrait
 * @package Grav\Common\Flex\Page\Traits
 */
trait PageAuthorsTrait
{
    /** @var array<int,UserInterface> */
    private $_authors;

    /**
     * Returns true if object has the named author.
     *
     * @param string $username
     * @return bool
     */
    public function hasAuthor(string $username): bool
    {
        $authors = (array)$this->getNestedProperty('header.authors');
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
            $this->_authors = (array)$this->loadAuthors($this->getNestedProperty('header.authors'));
        }

        return $this->_authors;
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
                throw new \InvalidArgumentException('Iterable should return username (string).', 500);
            }
            $list[] = $accounts->load($username);
        }

        return $list;
    }

    /**
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @return bool|null
     */
    protected function isAuthorizedOverride(UserInterface $user, string $action, string $scope): ?bool
    {
        $authorized = $this->isAuthorizedByGroup($user, $action, $scope);

        return $authorized ?? parent::isAuthorizedOverride($user, $action, $scope);
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
     * @return bool|null
     */
    protected function isAuthorizedByGroup(UserInterface $user, string $action, string $scope): ?bool
    {
        $authorized = null;

        // In admin we want to check against group permissions.
        $groups = $this->loadPermissions($user);
        /** @var Access $access */
        foreach ($groups as $access) {
            $auth = $access->authorize($action, $scope);
            if (is_bool($auth)) {
                if ($auth === false) {
                    return false;
                }
                $authorized = true;
            }
        }

        return $authorized;
    }

    /**
     * @param UserInterface $user
     * @return array
     */
    protected function loadPermissions(UserInterface $user): array
    {
        $permissions = $this->getNestedProperty('header.permissions');
        if (empty($permissions)) {
            return [];
        }

        $list = [];
        foreach ($permissions as $group => $access) {
            if (is_string($access)) {
                $access = $this->resolvePermissions($access);
            }
            if ($group === 'author') {
                // Special case for authors.
               if ($this->hasAuthor($user->username)) {
                    $list[$group] = new Access($permissions);
                }
            } else {
                $groups = (array)$user->groups;
                if (in_array($groups, $groups, true)) {
                    $list[$group] = new Access($permissions);
                }
            }
            $list[$group] = $access;
        }

        return $list;
    }

    /**
     * @param string $access
     * @return array
     */
    protected function resolvePermissions(string $access): array
    {
        static $rules = [
            'c' => 'create',
            'r' => 'read',
            'u' => 'update',
            'd' => 'delete',
            'p' => 'publish'
        ];
        static $ops = ['+' => true, '-' => false];

        $len = strlen($access);
        $op = true;
        $list = [];
        for($count=0; $count<$len; $count++) {
            $letter = $access[$count];
            if (isset($rules[$letter])) {
               $list[$rules[$letter]] = $op;
               $op = true;
            } elseif (isset($ops[$letter])) {
                $op = $ops[$letter];
            }
        }

        return $list;
    }

    abstract public function getNestedProperty($property, $default = null, $separator = null);
    abstract protected function loadAccounts(): ?UserCollectionInterface;

}
