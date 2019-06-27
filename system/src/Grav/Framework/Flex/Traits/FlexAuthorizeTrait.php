<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Traits;

use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;

/**
 * Implements basic ACL
 */
trait FlexAuthorizeTrait
{
    private $_authorize = '%s.flex-object.%s';

    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null) : bool
    {
        if (null === $user) {
            /** @var UserInterface $user */
            $user = Grav::instance()['user'] ?? null;
        }

        return $user && ($this->isAuthorizedAction($user, $action, $scope) || $this->isAuthorizedSuperAdmin($user));
    }

    protected function isAuthorizedSuperAdmin(UserInterface $user): bool
    {
        return $user->authorize('admin.super');
    }

    protected function isAuthorizedAction(UserInterface $user, string $action, string $scope = null) : bool
    {
        $scope = $scope ?? isset(Grav::instance()['admin']) ? 'admin' : 'site';

        if ($action === 'save' && $this instanceof FlexObjectInterface) {
            $action = $this->exists() ? 'update' : 'create';
        }

        $directory = $this instanceof FlexDirectory ? $this : $this->getFlexDirectory();
        $config = $directory->getConfig();
        $allowed = $config->get("{$scope}.actions.{$action}") ?? $config->get("actions.{$action}") ?? true;

        return $allowed && $user->authorize(sprintf($this->_authorize, $scope, $action));
    }

    protected function setAuthorizeRule(string $authorize) : void
    {
        $this->_authorize = $authorize;
    }
}
