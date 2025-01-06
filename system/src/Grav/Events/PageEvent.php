<?php

/**
 * @package    Grav\Events
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Events;

use Grav\Framework\Flex\Flex;
use RocketTheme\Toolbox\Event\Event;

class PageEvent extends Event
{
    public $page;
}
