<?php

/**
 * @package    Grav\Events
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Events;

use Grav\Common\Grav;
use Grav\Common\Plugins;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugins Loaded Event
 *
 * This event is called from InitializeProcessor.
 *
 * This is the first event plugin can see. Please avoid using this event if possible.
 *
 * @property Grav $grav Grav container.
 * @property Plugins $plugins Plugins instance.
 */
class PluginsLoadedEvent extends Event
{
    /** @var Grav */
    public $grav;
    /** @var Plugins */
    public $plugins;

    /**
     * PluginsLoadedEvent constructor.
     * @param Grav $grav
     * @param Plugins $plugins
     */
    public function __construct(Grav $grav, Plugins $plugins)
    {
        $this->grav = $grav;
        $this->plugins = $plugins;
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'plugins' => $this->plugins
        ];
    }
}
