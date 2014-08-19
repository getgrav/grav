<?php
namespace Grav\Component\EventDispatcher;

use \Symfony\Component\EventDispatcher\Event as BaseEvent;

class EventDispatcher extends \Symfony\Component\EventDispatcher\EventDispatcher
{
    public function dispatch($eventName, BaseEvent $event = null)
    {
        if (null === $event) {
            $event = new Event();
        }

        return parent::dispatch($eventName, $event);
    }
}
