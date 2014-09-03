<?php
namespace Grav\Common;

use Grav\Component\Data\Blueprints;
use Grav\Component\Data\Data;
use Grav\Component\Filesystem\File;
use Grav\Component\EventDispatcher\EventDispatcher;
use Grav\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Plugins object holds an array of all the plugin objects that
 * Grav knows about
 *
 * @author RocketTheme
 * @license MIT
 */
class Plugins extends Iterator
{
    protected $grav;

    public function __construct(Grav $grav) {
        $this->grav = $grav;
    }

    /**
     * Recurses through the plugins directory creating Plugin objects for each plugin it finds.
     *
     * @return array|Plugin[] array of Plugin objects
     * @throws \RuntimeException
     */
    public function init()
    {
        /** @var Config $config */
        $config = $this->grav['config'];
        $plugins = (array) $config->get('plugins');

        /** @var EventDispatcher $events */
        $events = $this->grav['events'];

        foreach ($plugins as $plugin => $data) {
            if (empty($data['enabled'])) {
                // Only load enabled plugins.
                continue;
            }

            $filePath = $this->grav['locator']('plugin://' . $plugin . DS . $plugin . PLUGIN_EXT);
            if (!is_file($filePath)) {
                throw new \RuntimeException(sprintf("Plugin '%s' enabled but not found!", $filePath, $plugin));
            }

            require_once $filePath;

            $pluginClass = 'Grav\\Plugin\\'.ucfirst($plugin).'Plugin';

            if (!class_exists($pluginClass)) {
                throw new \RuntimeException(sprintf("Plugin '%s' class not found!", $plugin));
            }

            $instance = new $pluginClass($this->grav, $config);
            if ($instance instanceof EventSubscriberInterface) {
                $events->addSubscriber($instance);
            }
        }

        $instance = $this->grav['themes']->load();
        $instance->configure();
        if ($instance instanceof EventSubscriberInterface) {
            $events->addSubscriber($instance);
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
    static public function all()
    {
        $list = array();
        $iterator = new \DirectoryIterator('plugin://');

        /** @var \DirectoryIterator $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }

            $type = $directory->getBasename();
            $list[$type] = self::get($type);
        }

        ksort($list);

        return $list;
    }

    static public function get($name)
    {
        $blueprints = new Blueprints("plugin://{$name}");
        $blueprint = $blueprints->get('blueprints');
        $blueprint->name = $name;

        // Load default configuration.
        $file = File\Yaml::instance("plugin://{$name}/{$name}.yaml");
        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $file = File\Yaml::instance("user://config/plugins/{$name}.yaml");
        $obj->merge($file->content());

        // Save configuration always to user/config.
        $obj->file($file);

        return $obj;
    }
}
