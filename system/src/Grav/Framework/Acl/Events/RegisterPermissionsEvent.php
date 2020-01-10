<?php

/**
 * @package    Grav\Framework\Acl
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Acl\Events;

use Grav\Framework\Acl\Permissions;

class RegisterPermissionsEvent
{
    /** @var Permissions */
    public $permissions;

    public function __construct(Permissions $permissions)
    {
        $this->permissions = $permissions;
    }
}
