<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Plugins extends Iterator
{
    public $formFieldTypes;

    public function __construct()
    {
        parent::__construct();

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $iterator = $locator->getIterator('plugins://');

        $plugins = [];
        foreach($iterator as $directory) {
            if (!$directory->isDir()) {
                continue;
            }
            $plugins[] = $directory->getBasename();
        }

        natsort($plugins);

        foreach ($plugins as $plugin) {
            $this->add($this->loadPlugin($plugin));
        }
    }

    /**
     * @return $this
     */
    public function setup()
    {
        $blueprints = [];
        $formFields = [];

        /** @var Plugin $plugin */
        foreach ($this->items as $plugin) {
            if (isset($plugin->features['blueprints'])) {
                $blueprints["plugin://{$plugin->name}/blueprints"] = $plugin->features['blueprints'];
            }
            if (method_exists($plugin, 'getFormFieldTypes')) {
                $formFields[get_class($plugin)] = isset($plugin->features['formfields']) ? $plugin->features['formfields'] : 0;
            }
        }

        if ($blueprints) {
            // Order by priority.
            arsort($blueprints);

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->addPath('blueprints', '', array_keys($blueprints), 'system/blueprints');
        }

        if ($formFields) {
            // Order by priority.
            arsort($formFields);

            $list = [];
            foreach ($formFields as $className => $priority) {
                $plugin = $this->items[$className];
                $list += $plugin->getFormFieldTypes();
            }

            $this->formFieldTypes = $list;
        }

        return $this;
    }

    /**
     * Registers all plugins.
     *
     * @return array|Plugin[] array of Plugin objects
     * @throws \RuntimeException
     */
    public function init()
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        /** @var EventDispatcher $events */
        $events = $grav['events'];

        foreach ($this->items as $instance) {
            // Register only enabled plugins.
            if ($config["plugins.{$instance->name}.enabled"] && $instance instanceof Plugin) {
                $instance->setConfig($config);
                $events->addSubscriber($instance);
            }
        }

        return $this->items;
    }

    /**
     * Add a plugin
     *
     * @param $plugin
     */
    public function add($plugin)
    {
        if (is_object($plugin)) {
            $this->items[get_class($plugin)] = $plugin;
        }
    }

    /**
     * Return list of all plugin data with their blueprints.
     *
     * @return array
     */
    public static function all()
    {
        $plugins = Grav::instance()['plugins'];
        $list = [];

        foreach ($plugins as $instance) {
            $name = $instance->name;
            $result = self::get($name);

            if ($result) {
                $list[$name] = $result;
            }
        }

        return $list;
    }

    /**
     * Get a plugin by name
     *
     * @param string $name
     *
     * @return Data|null
     */
    public static function get($name)
    {
        $blueprints = new Blueprints('plugins://');
        $blueprint = $blueprints->get("{$name}/blueprints");

        // Load default configuration.
        $file = CompiledYamlFile::instance("plugins://{$name}/{$name}" . YAML_EXT);

        // ensure this is a valid plugin
        if (!$file->exists()) {
            return null;
        }

        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $obj->merge(Grav::instance()['config']->get('plugins.' . $name) ?: []);

        // Save configuration always to user/config.
        $file = CompiledYamlFile::instance("config://plugins/{$name}.yaml");
        $obj->file($file);

        return $obj;
    }

    protected function loadPlugin($name)
    {
        $grav = Grav::instance();
        $locator = $grav['locator'];

        $filePath = $locator->findResource('plugins://' . $name . DS . $name . PLUGIN_EXT);
        if (!is_file($filePath)) {
            $grav['log']->addWarning(
                sprintf("Plugin '%s' enabled but not found! Try clearing cache with `bin/grav clear-cache`", $name)
            );
            return null;
        }

        require_once $filePath;

        $pluginClassName = 'Grav\\Plugin\\' . ucfirst($name) . 'Plugin';
        if (!class_exists($pluginClassName)) {
            $pluginClassName = 'Grav\\Plugin\\' . $grav['inflector']->camelize($name) . 'Plugin';
            if (!class_exists($pluginClassName)) {
                throw new \RuntimeException(sprintf("Plugin '%s' class not found! Try reinstalling this plugin.", $name));
            }
        }
        return new $pluginClassName($name, $grav);
    }

}
