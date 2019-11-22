<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Traits;

use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * Implements basic ACL
 */
trait FlexAuthorizeTrait
{
    /** @var string */
    private $_authorize = '%s.flex-object.%s';

    /**
     * Check if user is authorized for the action.
     *
     * Note: There are two deny values: denied (false), not set (null). This allows chaining multiple rules together
     * when the previous rules were not matched.
     *
     * To override the default behavior, please use isAuthorizedOverride().
     *
     * @param string $action
     * @param string|null $scope
     * @param UserInterface|null $user
     * @return bool|null
     * @final
     */
    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null): ?bool
    {
        $action = $this->getAuthorizeAction($action);
        $scope = $scope ?? $this->getAuthorizeScope();
        $user = $user ?? $this->getActiveUser();
        if (null === $user) {
            return false;
        }

        // Finally authorize against given action.
        return $this->isAuthorizedOverride($user, $action, $scope);
    }

    /**
     * Please override this method
     *
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @return bool|null
     */
    protected function isAuthorizedOverride(UserInterface $user, string $action, string $scope): ?bool
    {
        return $this->isAuthorizedAction($user, $action, $scope);
    }

    /**
     * Check if user is authorized for the action.
     *
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @return bool|null
     */
    protected function isAuthorizedAction(UserInterface $user, string $action, string $scope): ?bool
    {
        // Check if the action has been denied in the flex type configuration.
        $directory = $this instanceof FlexDirectory ? $this : $this->getFlexDirectory();
        $config = $directory->getConfig();
        $allowed = $config->get("{$scope}.actions.{$action}") ?? $config->get("actions.{$action}") ?? true;
        if (false === $allowed) {
            return false;
        }

        // TODO: Not needed anymore with flex users, remove in 2.0.
        $auth = $user instanceof FlexObjectInterface ? null : $user->authorize('admin.super');
        if (true === $auth) {
            return true;
        }

        // Finally authorize the action.
        return $user->authorize($this->getAuthorizeRule($scope, $action));
    }

    /**
     * @param UserInterface $user
     * @return bool|null
     * @deprecated 1.7 Not needed for Flex Users.
     */
    protected function isAuthorizedSuperAdmin(UserInterface $user): ?bool
    {
        // Action authorization includes super user authorization if using Flex Users.
        if ($user instanceof FlexObjectInterface) {
            return null;
        }

        return $user->authorize('admin.super');
    }

    /**
     * @param string $authorize
     */
    protected function setAuthorizeRule(string $authorize): void
    {
        $this->_authorize = $authorize;
    }

    /**
     * @param string $scope
     * @param string $action
     * @return string
     */
    protected function getAuthorizeRule(string $scope, string $action): string
    {
        return sprintf($this->_authorize, $scope, $action);
    }
}
