<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Plugins object holds an array of all the plugin objects that
 * Grav knows about
 *
 * @author RocketTheme
 * @license MIT
 */
class Plugins extends Iterator
{
    use GravTrait;

    public function __construct()
    {
        parent::__construct();

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $iterator = $locator->getIterator('plugins://');
        foreach ($iterator as $directory) {
            if (!$directory->isDir()) {
                continue;
            }

            $plugin = $directory->getBasename();

            $this->add($this->loadPlugin($plugin));
        }
    }

    /**
     * Registers all plugins.
     *
     * @return array|Plugin[] array of Plugin objects
     * @throws \RuntimeException
     */
    public function init()
    {
        /** @var Config $config */
        $config = self::getGrav()['config'];

        /** @var EventDispatcher $events */
        $events = self::getGrav()['events'];

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
        $plugins = self::getGrav()['plugins'];
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

    public static function get($name)
    {
        $blueprints = new Blueprints('plugins://');
        $blueprint = $blueprints->get("{$name}/blueprints");
        $blueprint->name = $name;

        // Load default configuration.
        $file = CompiledYamlFile::instance("plugins://{$name}/{$name}" . YAML_EXT);

        // ensure this is a valid plugin
        if (!$file->exists()) {
            return null;
        }

        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $obj->merge(self::getGrav()['config']->get('plugins.' . $name) ?: []);

        // Save configuration always to user/config.
        $file = CompiledYamlFile::instance("config://plugins/{$name}.yaml");
        $obj->file($file);

        return $obj;
    }

    protected function loadPlugin($name)
    {
        $grav = self::getGrav();
        $locator = $grav['locator'];

        $filePath = $locator->findResource('plugins://' . $name . DS . $name . PLUGIN_EXT);
        if (!is_file($filePath)) {
            self::getGrav()['log']->addWarning(
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
        return new $pluginClassName($name, self::getGrav());
    }

}
