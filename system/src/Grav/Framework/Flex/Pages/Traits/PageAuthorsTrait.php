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
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;

/**
 * Implements PageAuthorsTrait
 * @package Grav\Common\Flex\Page\Traits
 */
trait PageAuthorsTrait
{
    use FlexAuthorizeTrait {
        isAuthorized as private isFlexAuthorized;
    }

    /** @var array<int,UserInterface> */
    private $_authors;

    /**
     * @param string $username
     * @return bool
     */
    public function hasAuthor(string $username): bool
    {
        $authors = $this->getNestedProperty('header.authors');
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
     * @return array<int,UserInterface>
     */
    public function getAuthors(): array
    {
        if (null === $this->_authors) {
            $this->_authors = $this->loadAuthors($this->getNestedProperty('header.authors'));
        }

        return $this->_authors;
    }

    /**
     * @param string $action
     * @param string|null $scope
     * @param UserInterface|null $user
     * @return bool
     */
    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null): bool
    {
        $scope = $scope ?? $this->getAuthorizeScope();
        $groups = $this->loadPermissions($user);
        $authorized = null;
        if ($scope === 'admin') {
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
        }

        return $authorized ?? $this->isFlexAuthorized($action, $scope, $user);
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
     * @return array
     */
    protected function loadPermissions(?UserInterface $user): array
    {
        $user = $user ?? $this->getCurrentUser();
        $permissions = $this->getNestedProperty('header.permissions');
        if (!$user || empty($permissions)) {
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
