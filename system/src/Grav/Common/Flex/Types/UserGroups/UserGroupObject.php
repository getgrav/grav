<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\UserGroups;

use Grav\Common\Flex\FlexObject;
use Grav\Common\Grav;
use Grav\Common\User\Access;
use Grav\Common\User\Interfaces\UserGroupInterface;
use Grav\Common\User\Interfaces\UserInterface;
use RuntimeException;
use function in_array;
use function is_bool;

/**
 * Flex User Group
 *
 * @package Grav\Common\User
 *
 * @property string $groupname
 * @property Access $access
 */
class UserGroupObject extends FlexObject implements UserGroupInterface
{
    /** @var Access */
    protected $_access;
    /** @var array|null */
    protected $access;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'authorize' => false,
        ] + parent::getCachedMethods();
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getProperty('readableName');
    }

    /**
     * Checks user authorization to the action.
     *
     * @param  string $action
     * @param  string|null $scope
     * @param  bool $super  False if the group may not fall back to its super user access.
     * @return bool|null
     */
    public function authorize(string $action, ?string $scope = null, bool $super = true): ?bool
    {
        if ($scope === 'test') {
            $scope = null;
        } elseif (!$this->getProperty('enabled', true)) {
            return null;
        }

        $access = $this->getAccess();

        $authorized = $access->authorize($action, $scope);
        if (is_bool($authorized)) {
            return $authorized;
        }

        return $super && $access->authorize('admin.super') ? true : null;
    }

    /**
     * Save user group
     *
     * @return static
     */
    public function save()
    {
        // Prevent overwriting an existing group, access map included, when a user who
        // is only allowed to create groups submits an already-taken group name. The
        // storage names the row after its key field (`groupname`) whenever the row has
        // one, so that is the key to check, not the key of the object.
        $storage = $this->getFlexDirectory()->getStorage();
        $oldKey = (string)($this->getMetaData()['storage_key'] ?? '');
        $newKey = (string)($this->getElement($storage->getKeyField()) ?? $this->getStorageKey());
        if ($newKey !== $oldKey && $storage->hasKey($newKey)) {
            throw new RuntimeException('User group with this name already exists');
        }

        return parent::save();
    }

    public static function groupNames(): array
    {
        $groups = [];
        $user_groups = Grav::instance()['user_groups'] ?? [];

        foreach ($user_groups as $key => $group) {
            $groups[$key] = $group->readableName ?: $key;
        }

        return $groups;
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
        // Managing groups does not reach the super-admin tier either: the line
        // UserObject::isAuthorizedOverride() holds for accounts would be worth
        // little if a user manager could switch off, re-permission or delete the
        // group its members are super-admins through. Read straight from the ACL,
        // so a group that is switched off is guarded the same as one that is on.
        if (in_array($action, ['update', 'delete'], true)
            && $this->hasSuperAccess()
            && !$this->actorHasSuperAccess($user, $isMe)
        ) {
            return false;
        }

        return parent::isAuthorizedOverride($user, $action, $scope, $isMe);
    }

    /**
     * Whether the access map of this group grants super-admin access to its members.
     *
     * @return bool
     */
    protected function hasSuperAccess(): bool
    {
        $access = $this->getAccess();

        return $access->authorize('admin.super') === true || $access->authorize('api.super') === true;
    }

    /**
     * @param UserInterface $user
     * @param bool $isMe
     * @return bool
     */
    protected function actorHasSuperAccess(UserInterface $user, bool $isMe): bool
    {
        $scope = $isMe ? null : 'test';

        return $user->authorize('admin.super', $scope) === true
            || $user->authorize('api.super', $scope) === true;
    }

    /**
     * @return Access
     */
    protected function getAccess(): Access
    {
        if (null === $this->_access) {
            $this->getProperty('access');
        }

        return $this->_access;
    }

    /**
     * @return array
     */
    protected function offsetLoad_access(mixed $value): array
    {
        if (!$value instanceof Access) {
            $value = new Access($value);
        }

        $this->_access = $value;

        return $value->jsonSerialize();
    }

    /**
     * @return array
     */
    protected function offsetPrepare_access(mixed $value): array
    {
        return $this->offsetLoad_access($value);
    }

    /**
     * @param array|null $value
     * @return array|null
     */
    protected function offsetSerialize_access(?array $value): ?array
    {
        return $value;
    }
}
