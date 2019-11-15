<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

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
        $permissions = $this->loadPermissions();

        return $this->isFlexAuthorized($action, $scope, $user);
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

    protected function loadPermissions(): array
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
            $list[$group] = $access;
        }

        return $list;
    }

    protected function resolvePermissions(string $access): array
    {
        // FIXME:
        return [];
    }

    abstract public function getNestedProperty($property, $default = null, $separator = null);
    abstract protected function loadAccounts(): ?UserCollectionInterface;

}
