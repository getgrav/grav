<?php
/**
 * @package    Grav.Common.User
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\Events;

use Grav\Common\User\User;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class UserLoginEvent
 * @package Grav\Common\User\Events
 *
 * @property int    $status
 * @property array  $credentials
 * @property array  $options
 * @property User   $user
 * @property string $message
 *
 */
class UserLoginEvent extends Event
{
    /**
     * Undefined event state.
     */
    const AUTHENTICATION_UNDEFINED = 0;

    /**
     * onUserAuthenticate success.
     */
    const AUTHENTICATION_SUCCESS = 1;

    /**
     * onUserAuthenticate fails on bad username/password.
     */
    const AUTHENTICATION_FAILURE = 2;

    /**
     * onUserAuthenticate fails on auth cancellation.
     */
    const AUTHENTICATION_CANCELLED = 4;

    /**
     * onUserAuthorizeLogin fails on expired account.
     */
    const AUTHORIZATION_EXPIRED = 8;

    /**
     * onUserAuthorizeLogin fails for other reasons.
     */
    const AUTHORIZATION_DENIED = 16;

    public function __construct(array $items = [])
    {
        $defaults = [
            'credentials' => ['username' => '', 'password' => ''],
            'options' => ['remember_me' => false],
            'status' => static::AUTHENTICATION_UNDEFINED,
            'user' => null,
            'message' => ''
        ];

        parent::__construct(array_merge_recursive($defaults, $items));

        $username = $this['credentials']['username'];
        $this['user'] = $username ? User::load($username, false) : new User;
    }

    public function removeCredentials()
    {
        unset($this['credentials']);
    }
}
