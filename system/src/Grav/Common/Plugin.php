<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Data\Data;
use Grav\Common\Page\Page;
use Grav\Common\Config\Config;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;
use RocketTheme\Toolbox\File\YamlFile;
use Symfony\Component\Console\Exception\LogicException;

class Plugin implements EventSubscriberInterface, \ArrayAccess
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $features = [];

    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var Config
     */
    protected $config;

    protected $active = true;
    protected $blueprint;

    /**
     * By default assign all methods as listeners using the default priority.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        $methods = get_class_methods(get_called_class());

        $list = [];
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
     * @param string $name
     * @param Grav   $grav
     * @param Config $config
     */
    public function __construct($name, Grav $grav, Config $config = null)
    {
        $this->name = $name;
        $this->grav = $grav;
        if ($config) {
            $this->setConfig($config);
        }
    }

    /**
     * @param Config $config
     * @return $this
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get configuration of the plugin.
     *
     * @return Config
     */
    public function config()
    {
        return $this->config["plugins.{$this->name}"];
    }

    /**
     * Determine if this is running under the admin
     *
     * @return bool
     */
    public function isAdmin()
    {
        return Utils::isAdminPlugin();
    }

    /**
     * Determine if this route is in Admin and active for the plugin
     *
     * @param $plugin_route
     * @return bool
     */
    protected function isPluginActiveAdmin($plugin_route)
    {
        $should_run = false;

        $uri = $this->grav['uri'];

        if (strpos($uri->path(), $this->config->get('plugins.admin.route') . '/' . $plugin_route) === false) {
            $should_run = false;
        } elseif (isset($uri->paths()[1]) && $uri->paths()[1] == $plugin_route) {
            $should_run = true;
        }

        return $should_run;
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
                $dispatcher->addListener($eventName, [$this, $params]);
            } elseif (is_string($params[0])) {
                $dispatcher->addListener($eventName, [$this, $params[0]], isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $dispatcher->addListener($eventName, [$this, $listener[0]], isset($listener[1]) ? $listener[1] : 0);
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
                $dispatcher->removeListener($eventName, [$this, $params]);
            } elseif (is_string($params[0])) {
                $dispatcher->removeListener($eventName, [$this, $params[0]]);
            } else {
                foreach ($params as $listener) {
                    $dispatcher->removeListener($eventName, [$this, $listener[0]]);
                }
            }
        }
    }

    /**
     * Whether or not an offset exists.
     *
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function offsetExists($offset)
    {
        $this->loadBlueprint();

        if ($offset === 'title') {
            $offset = 'name';
        }
        return isset($this->blueprint[$offset]);
    }

    /**
     * Returns the value at specified offset.
     *
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        $this->loadBlueprint();

        if ($offset === 'title') {
            $offset = 'name';
        }
        return isset($this->blueprint[$offset]) ? $this->blueprint[$offset] : null;
    }

    /**
     * Assigns a value to the specified offset.
     *
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     */
    public function offsetSet($offset, $value)
    {
        throw new LogicException(__CLASS__ . ' blueprints cannot be modified.');
    }

    /**
     * Unsets an offset.
     *
     * @param mixed $offset  The offset to unset.
     */
    public function offsetUnset($offset)
    {
        throw new LogicException(__CLASS__ . ' blueprints cannot be modified.');
    }

    /**
     * This function will search a string for markdown links in a specific format.  The link value can be
     * optionally compared against via the $internal_regex and operated on by the callback $function
     * provided.
     *
     * format: [plugin:myplugin_name](function_data)
     *
     * @param string   $content        The string to perform operations upon
     * @param callable $function       The anonymous callback function
     * @param string   $internal_regex Optional internal regex to extra data from
     *
     * @return string
     */
    protected function parseLinks($content, $function, $internal_regex = '(.*)')
    {
        $regex = '/\[plugin:(?:' . $this->name . ')\]\(' . $internal_regex . '\)/i';

        return preg_replace_callback($regex, $function, $content);
    }

    /**
     * Merge global and page configurations.
     *
     * @param Page $page The page to merge the configurations with the
     *                       plugin settings.
     * @param mixed $deep false = shallow|true = recursive|merge = recursive+unique
     * @param array $params Array of additional configuration options to
     *                       merge with the plugin settings.
     * @param string $type Is this 'plugins' or 'themes'
     *
     * @return Data
     */
    protected function mergeConfig(Page $page, $deep = false, $params = [], $type = 'plugins')
    {
        $class_name = $this->name;
        $class_name_merged = $class_name . '.merged';
        $defaults = $this->config->get($type . '.' . $class_name, []);
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
            $header = $this->mergeArrays($deep, $defaults, $config);

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
        $header = $this->mergeArrays($deep, $header, $params);

        // Return configurations as a new data config class
        return new Data($header);
    }

    /**
     * Merge arrays based on deepness
     *
     * @param bool $deep
     * @param $array1
     * @param $array2
     * @return array|mixed
     */
    private function mergeArrays($deep = false, $array1, $array2)
    {
        if ($deep == 'merge') {
            return Utils::arrayMergeRecursiveUnique($array1, $array2);
        } elseif ($deep === true) {
            return array_replace_recursive($array1, $array2);
        } else {
            return array_merge($array1, $array2);
        }
    }

    /**
     * Persists to disk the plugin parameters currently stored in the Grav Config object
     *
     * @param string $plugin_name The name of the plugin whose config it should store.
     *
     * @return true
     */
    public static function saveConfig($plugin_name)
    {
        if (!$plugin_name) {
            return false;
        }

        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = 'config://plugins/' . $plugin_name . '.yaml';
        $file = YamlFile::instance($locator->findResource($filename, true, true));
        $content = $grav['config']->get('plugins.' . $plugin_name);
        $file->save($content);
        $file->free();

        return true;
    }

    /**
     * Simpler getter for the plugin blueprint
     *
     * @return mixed
     */
    public function getBlueprint()
    {
        if (!$this->blueprint) {
            $this->loadBlueprint();
        }
        return $this->blueprint;
    }

    /**
     * Load blueprints.
     */
    protected function loadBlueprint()
    {
        if (!$this->blueprint) {
            $grav = Grav::instance();
            $plugins = $grav['plugins'];
            $this->blueprint = $plugins->get($this->name)->blueprints();
        }
    }
}
