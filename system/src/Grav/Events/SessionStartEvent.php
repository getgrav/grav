<?php

/**
 * @package    Grav\Events
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Events;

use Grav\Framework\Session\Session;

class SessionStartEvent
{
    /** @var Session */
    public $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function __debugInfo(): array
    {
        return (array)$this;
    }
}
