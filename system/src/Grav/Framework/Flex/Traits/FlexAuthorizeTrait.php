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

    public function authorize(string $action, ?string $scope = null) : bool
    {
        $grav = Grav::instance();
        if (!isset($grav['user'])) {
            throw new \RuntimeException(__TRAIT__ . '::' . __METHOD__ . ' requires user service');
        }

        /** @var User $user */
        $user = $grav['user'];

        $scope = $scope ?? isset($grav['admin']) ? 'admin' : 'site';

        if ($action === 'save') {
            $action = $this->exists() ? 'update' : 'create';
        }

        return $user->authorize(sprintf($this->_authorize, $scope, $action)) || $user->authorize('admin.super');
    }

    protected function setAuthorizeRule(string $authorize) : void
    {
        $this->_authorize = $authorize;
    }
}
