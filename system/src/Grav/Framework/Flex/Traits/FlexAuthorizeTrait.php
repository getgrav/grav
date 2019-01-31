<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Traits;

use Grav\Common\Grav;
use Grav\Common\User\User;

/**
 * Implements basic ACL
 */
trait FlexAuthorizeTrait
{
    private $_authorize = '%s.flex-object.%s';

    public function authorize(string $action, string $scope = null) : bool
    {
        /** @var User $user */
        $user = Grav::instance()['user'];

        return $this->authorizeAction($user, $action, $scope) || $this->authorizeSuperAdmin($user);
    }

    protected function authorizeSuperAdmin(User $user): bool
    {
        return $user->authorize('admin.super');
    }

    protected function authorizeAction(User $user, string $action, string $scope = null) : bool
    {
        $scope = $scope ?? isset(Grav::instance()['admin']) ? 'admin' : 'site';

        if ($action === 'save') {
            $action = $this->exists() ? 'update' : 'create';
        }

        return $user->authorize(sprintf($this->_authorize, $scope, $action));
    }

    protected function setAuthorizeRule(string $authorize) : void
    {
        $this->_authorize = $authorize;
    }
}
