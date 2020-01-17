<?php

/**
 * @package    Grav\Events
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Events;

use Grav\Common\Grav;
use Grav\Common\Plugins;

class PluginsLoadedEvent
{
    /** @var Grav */
    public $grav;
    /** @var Plugins */
    public $plugins;

    public function __construct(Grav $grav, Plugins $plugins)
    {
        $this->grav = $grav;
        $this->plugins = $plugins;
    }

    public function __debugInfo(): array
    {
        return [
            'plugins' => $this->plugins
        ];
    }
}
