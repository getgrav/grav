<?php

/**
 * @package    Grav\Events
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Events;

use Grav\Framework\Acl\Permissions;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Permissions Register Event
 *
 * This event is called the first time $grav['permissions'] is being called.
 *
 * Use this event to register any new permission types you use in your plugins.
 *
 * @property Permissions $permissions Permissions instance.
 */
class PermissionsRegisterEvent extends Event
{
    /** @var Permissions */
    public $permissions;

    /**
     * PermissionsRegisterEvent constructor.
     * @param Permissions $permissions
     */
    public function __construct(Permissions $permissions)
    {
        $this->permissions = $permissions;
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return (array)$this;
    }
}
