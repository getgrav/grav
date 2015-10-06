<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\GravTrait;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;

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

    /**
     * Recurses through the plugins directory creating Plugin objects for each plugin it finds.
     *
     * @return array|Plugin[] array of Plugin objects
     * @throws \RuntimeException
     */
    public function init()
    {
        /** @var Config $config */
        $config = self::getGrav()['config'];
        $plugins = (array) $config->get('plugins');

        $inflector = self::getGrav()['inflector'];

        /** @var EventDispatcher $events */
        $events = self::getGrav()['events'];

        foreach ($plugins as $plugin => $data) {
            if (empty($data['enabled'])) {
                // Only load enabled plugins.
                continue;
            }

            $locator = self::getGrav()['locator'];
            $filePath = $locator->findResource('plugins://' . $plugin . DS . $plugin . PLUGIN_EXT);
            if (!is_file($filePath)) {
                self::getGrav()['log']->addWarning(sprintf("Plugin '%s' enabled but not found! Try clearing cache with `bin/grav clear-cache`", $plugin));
                continue;
            }

            require_once $filePath;

            $pluginClassFormat = [
                'Grav\\Plugin\\'.ucfirst($plugin).'Plugin',
                'Grav\\Plugin\\'.$inflector->camelize($plugin).'Plugin'
            ];
            $pluginClassName = false;

            foreach ($pluginClassFormat as $pluginClass) {
                if (class_exists($pluginClass)) {
                    $pluginClassName = $pluginClass;
                    break;
                }
            }

            if (false === $pluginClassName) {
                throw new \RuntimeException(sprintf("Plugin '%s' class not found! Try reinstalling this plugin.", $plugin));
            }

            $instance = new $pluginClassName($plugin, self::getGrav(), $config);
            if ($instance instanceof EventSubscriberInterface) {
                $events->addSubscriber($instance);
            }
        }

        return $this->items;
    }

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
        $list = array();
        $locator = Grav::instance()['locator'];

        $plugins = (array) $locator->findResources('plugins://', false);
        foreach ($plugins as $path) {
            $iterator = new \DirectoryIterator($path);

            /** @var \DirectoryIterator $directory */
            foreach ($iterator as $directory) {
                if (!$directory->isDir() || $directory->isDot()) {
                    continue;
                }

                $type = $directory->getBasename();
                $list[$type] = self::get($type);
            }
        }
        ksort($list);

        return $list;
    }

    public static function get($name)
    {
        $blueprints = new Blueprints('plugins://');
        $blueprint = $blueprints->get("{$name}/blueprints");
        $blueprint->name = $name;

        // Load default configuration.
        $file = CompiledYamlFile::instance("plugins://{$name}/{$name}.yaml");
        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $obj->merge(self::getGrav()['config']->get('plugins.' . $name) ?: []);

        // Save configuration always to user/config.
        $file = CompiledYamlFile::instance("config://plugins/{$name}.yaml");
        $obj->file($file);

        return $obj;
    }

}
