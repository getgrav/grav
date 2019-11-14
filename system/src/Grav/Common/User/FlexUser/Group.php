<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\FlexUser;

use Grav\Common\User\Access;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;

/**
 * Flex User Group
 *
 * @package Grav\Common\User
 *
 * @property string $groupname
 * @property Access $access
 */
class Group extends FlexObject
{
    use FlexAuthorizeTrait;

    /** @var Access|null */
    protected $_access;

    /** @var array|null */
    protected $access;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'authorize' => true,
        ] + parent::getCachedMethods();
    }

    /**
     * Checks user authorization to the action.
     *
     * @param  string $action
     * @param  string|null $scope
     * @return bool|null
     */
    public function authorize(string $action, string $scope = null): ?bool
    {
        if ($scope === 'test') {
            $scope = null;
        } else {
            if (!$this->getProperty('enabled', true)) {
                return null;
            }
        }

        $access = $this->getAccess();

        $authorized = $access->authorize($action, $scope);
        if (is_bool($authorized)) {
            return $authorized;
        }

        return $access->authorize('admin.super') ? true : null;
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
     * @param mixed $value
     * @return array
     */
    protected function offsetLoad_access($value): array
    {
        if (!$value instanceof Access) {
            $value = new Access($value);
        }

        $this->_access = $value;

        return $value->jsonSerialize();
    }

    /**
     * @param mixed $value
     * @return Access
     */
    protected function offsetPrepare_access($value): array
    {
        return $this->offsetLoad_access($value);
    }

    /**
     * @param Access|null $value
     * @return array|null
     */
    protected function offsetSerialize_access(?array $value): ?array
    {
        return $value;
    }
}
