<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;

/**
 * The Plugin object just holds the id and path to a plugin.
 *
 * @author RocketTheme
 * @license MIT
 */
class Plugin implements EventSubscriberInterface
{
    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var Config
     */
    protected $config;

    protected $active = true;

    /**
     * By default assign all methods as listeners using the default priority.
     *
     * @return array
     */
    public static function getSubscribedEvents() {
        $methods = get_class_methods(get_called_class());

        $list = array();
        foreach ($methods as $method) {
            if (strpos($method, 'on') === 0) {
                $list[$method] = [$method, 0];
            }
        }

        return $list;
    }

    /**
     * Constructor.
     *
     * @param Grav $grav
     * @param Config $config
     */
    public function __construct(Grav $grav, Config $config)
    {
        $this->grav = $grav;
        $this->config = $config;
    }

    public function isAdmin()
    {
        if (isset($this->grav['admin'])) {
            return true;
        }
        return false;
    }

    /**
     * @param array $events
     */
    protected function enable(array $events)
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->grav['events'];

        foreach ($events as $eventName => $params) {
            if (is_string($params)) {
                $dispatcher->addListener($eventName, array($this, $params));
            } elseif (is_string($params[0])) {
                $dispatcher->addListener($eventName, array($this, $params[0]), isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $dispatcher->addListener($eventName, array($this, $listener[0]), isset($listener[1]) ? $listener[1] : 0);
                }
            }
        }
    }
}
