<?php

namespace RocketTheme\Toolbox\Event;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Export;

if (!class_exists(__NAMESPACE__ . '\\BaseEvent', false)) {
    if (class_exists(\Symfony\Contracts\EventDispatcher\Event::class)) {
        /**
         * @internal Maps to the Symfony Contracts event when available.
         */
        abstract class BaseEvent extends \Symfony\Contracts\EventDispatcher\Event
        {
        }
    } elseif (class_exists(\Symfony\Component\EventDispatcher\Event::class)) {
        /**
         * @internal Fallback for legacy Symfony Event dispatcher during upgrades.
         */
        abstract class BaseEvent extends \Symfony\Component\EventDispatcher\Event
        {
        }
    } else {
        /**
         * @internal Minimal stop-propagation implementation used as a last resort.
         */
        abstract class BaseEvent
        {
            private bool $propagationStopped = false;

            public function isPropagationStopped(): bool
            {
                return $this->propagationStopped;
            }

            public function stopPropagation(): void
            {
                $this->propagationStopped = true;
            }
        }
    }
}

/**
 * Implements Symfony Event interface.
 *
 * @package RocketTheme\Toolbox\Event
 * @author RocketTheme
 * @license MIT
 * @deprecated Event classes will be removed in the future. Use PSR-14 implementation instead.
 */
class Event extends BaseEvent implements \ArrayAccess
{
    use ArrayAccess, Constructor, Export;

    /** @var array */
    protected array $items = [];
}
