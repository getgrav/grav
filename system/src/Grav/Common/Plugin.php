<?php
namespace Grav\Common;

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
     * @var \Grav\Common\string
     */
    protected $name;

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
     * @param string              $name
     * @param Grav                $grav
     * @param Config              $config
     */
    public function __construct($name, Grav $grav, Config $config)
    {
        $this->grav = $grav;
        $this->config = $config;
        $this->name = $name;
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
     * This function will search a string for markdown links in a specific format.  The link value can be
     * optionally compared against via the $internal_regex and operated on by the callback $function
     * provided.
     *
     * format: [plugin:myplugin_name](function_data)
     *
     * @param        $content           The string to perform operations upon
     * @param        $function          The anonymous callback function
     * @param string $internal_regex    Optional internal regex to extra data from
     *
     * @return string
     */
    protected function parseLinks($content, $function, $internal_regex = '(.*)')
    {
        $regex = '/\[plugin:(?:'.$this->name.')\]\('.$internal_regex.'\)/i';
        return preg_replace_callback($regex, $function, $content);
    }

    /**
     * Merge global and page configurations.
     *
     * @param Page  $page    The page to merge the configurations with the
     *                       plugin settings.
     * @param bool  $deep    Should you use deep or shallow merging
     * @param array $params  Array of additional configuration options to
     *                       merge with the plugin settings.
     *
     * @return \Grav\Common\Data\Data
     */
    protected function mergeConfig(Page $page, $deep = false, $params = [])
    {
        $class_name = $this->name;
        $class_name_merged = $class_name . '.merged';
        $defaults = $this->config->get('plugins.'. $class_name, []);
        $page_header = $page->header();
        $header = [];
        if (!isset($page_header->$class_name_merged) && isset($page_header->$class_name)) {
            // Get default plugin configurations and retrieve page header configuration
            $config = $page_header->$class_name;
            if (is_bool($config)) {
                // Overwrite enabled option with boolean value in page header
                $config = ['enabled' => $config];
            }
            // Merge page header settings using deep or shallow merging technique
            if ($deep) {
                $header = array_replace_recursive($defaults, $config);
            } else {
                $header = array_merge($defaults, $config);
            }
            // Create new config object and set it on the page object so it's cached for next time
            $page->modifyHeader($class_name_merged, new Data($header));
        } else if (isset($page_header->$class_name_merged)) {
            $merged = $page_header->$class_name_merged;
            $header = $merged->toArray();
        }
        if (empty($header)) {
            $header = $defaults;
        }
        // Merge additional parameter with configuration options
        if ($deep) {
            $header = array_replace_recursive($header, $params);
        } else {
            $header = array_merge($header, $params);
        }
        // Return configurations as a new data config class
        return new Data($header);
    }
}
