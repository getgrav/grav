<?php
namespace Grav\Common;

use Grav\Common\Inflector;
use Grav\Common\Data\Data;
use Grav\Common\Page\Page;
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
    public static function getSubscribedEvents()
    {
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

    /**
     * @param array $events
     */
    protected function disable(array $events)
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->grav['events'];

        foreach ($events as $eventName => $params) {
            if (is_string($params)) {
                $dispatcher->removeListener($eventName, array($this, $params));
            } elseif (is_string($params[0])) {
                $dispatcher->removeListener($eventName, array($this, $params[0]));
            } else {
                foreach ($params as $listener) {
                    $dispatcher->removeListener($eventName, array($this, $listener[0]));
                }
            }
        }
    }

    /**
     * Merge global and page configurations.
     *
     * @param  Page   $page The page to merge the configurations with the
     *                      plugin settings.
     */
    protected function mergeConfig(Page $page)
    {
        static $className;

        if ( is_null($className) ) {
            // Load configuration based on class name
            $reflector = new \ReflectionClass($this);

            // Remove namespace and trailing "Plugin" word
            $name = $reflector->getShortName();
            $name = substr($name, 0, -strlen('Plugin'));

            // Guess configuration path from class name
            $class_formats = array(
                strtolower($name),                # all lowercased
                Inflector::underscorize($name),   # underscored
            );

            $defaults = array();
            // Try to load configuration
            foreach ( $class_formats as $name ) {
                if ( !is_null($this->config->get('plugins.' . $name, NULL)) ) {
                    $className = $name;
                    break;
                }
            }
        }

        // Get default plugin configurations and retrieve page header configuration
        $plugin = (array) $this->config->get('plugins.' . $className, array());
        $header = (array) $page->header();

        // Create new config data class
        $config = new Data();

        // Join configuration options
        $config->setDefaults($header);
        $config->joinDefaults($className, $plugin);

        // Return configurations as a new data config class
        return $config;
    }
}
