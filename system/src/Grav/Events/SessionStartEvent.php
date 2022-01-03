<?php

/**
 * @package    Grav\Events
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Events;

use Grav\Framework\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugins Loaded Event
 *
 * This event is called from $grav['session']->start() right after successful session_start() call.
 *
 * @property SessionInterface $session Session instance.
 */
class SessionStartEvent extends Event
{
    /** @var SessionInterface */
    public $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function __debugInfo(): array
    {
        return (array)$this;
    }
}
