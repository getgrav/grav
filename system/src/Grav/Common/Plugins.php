<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Events\PluginsLoadedEvent;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use SplFileInfo;
use Grav\Common\Helpers\PluginAutoloader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function get_class;
use function is_object;

/**
 * Class Plugins
 * @package Grav\Common
 */
class Plugins extends Iterator
{
    /** @var array|null */
    public $formFieldTypes;

    /** @var bool */
    private $plugins_initialized = false;

    /** @var PluginAutoloader|null Index of the enabled plugins' Composer loaders, registered in front of them. */
    private $autoloader;

    /** Key prefix of the entries kept for disabled plugins whose main file was not loaded. */
    private const UNLOADED_PREFIX = 'unloaded:';

    /** @var string[]|null Names of the plugins found, until load() has run. */
    private $pending;

    /** @var array<string,string> Plugin name by lowercased main class name, for plugins whose main file has not been run. */
    private $unloaded = [];

    /**
     * Plugins constructor.
     */
    public function __construct()
    {
        parent::__construct();

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $iterator = $locator->getIterator('plugins://');

        $plugins = [];
        /** @var SplFileInfo $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir()) {
                continue;
            }
            $plugins[] = $directory->getFilename();
        }

        sort($plugins, SORT_NATURAL | SORT_FLAG_CASE);

        // No plugin file is run here. On a cold cache this constructor is reached while the
        // configuration is still being compiled (config -> blueprints -> form field types), so
        // whether a plugin is enabled is not known yet. The files are loaded by load(), from
        // setup() or init(), once it is. Until then a plugin's main class loads on first use.
        $this->pending = $plugins;
        foreach ($plugins as $plugin) {
            foreach ($this->pluginClassNames($plugin) as $className) {
                $this->unloaded[strtolower($className)] = $plugin;
            }
        }

        spl_autoload_register([$this, 'autoloadUnloadedPlugin']);
    }

    /**
     * Load the enabled plugins. A disabled plugin's main file is not run, so code at its file
     * scope (a require of a class that no longer matches core, say) cannot take the site down
     * (#4374). It stays in the list as a plain Plugin entry, so its blueprints, configuration
     * and GPM listing work as before, and its class still loads if anything refers to it.
     *
     * @return void
     */
    protected function load(): void
    {
        if (null === $this->pending) {
            return;
        }

        $plugins = $this->pending;
        $this->pending = null;

        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        foreach ($plugins as $plugin) {
            if (!$config->get("plugins.{$plugin}.enabled") && $this->pluginFile($plugin)) {
                $this->items[self::UNLOADED_PREFIX . $plugin] = new Plugin($plugin, $grav);
                continue;
            }

            $this->forgetUnloaded($plugin);
            $object = $this->loadPlugin($plugin);
            if ($object) {
                $this->add($object);
            }
        }
    }

    /**
     * Load the main class of a plugin whose file has not been run, the first time something
     * refers to that class (a blueprint `data-options@`, a class_exists() check, another plugin
     * calling it), as Grav 1.7 had every plugin's main class available.
     *
     * @param string $className
     * @return void
     * @internal
     */
    public function autoloadUnloadedPlugin(string $className): void
    {
        // Class names are case-insensitive: ai-pro declares AIProPlugin, not AiProPlugin.
        $name = $this->unloaded[strtolower($className)] ?? null;
        if (null === $name) {
            return;
        }

        $this->forgetUnloaded($name);
        $file = $this->pluginFile($name);
        if (!$file) {
            return;
        }

        try {
            // Local variables available in the file, as in loadPlugin(): $grav, $name, $file
            (static function (Grav $grav, string $name, string $file) {
                include_once $file;
            })(Grav::instance(), $name, $file);
        } catch (\Throwable $e) {
            Grav::instance()['log']->error(
                sprintf("Plugin '%s' could not be loaded: %s", $name, $e->getMessage())
            );
        }
    }

    /**
     * @param string $name
     * @return void
     */
    private function forgetUnloaded(string $name): void
    {
        foreach ($this->pluginClassNames($name) as $className) {
            unset($this->unloaded[strtolower($className)]);
        }
    }

    /**
     * @return $this
     */
    public function setup()
    {
        $this->load();

        $blueprints = [];
        $formFields = [];

        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        /** @var Plugin $plugin */
        foreach ($this->items as $plugin) {
            // Setup only enabled plugins.
            if ($config["plugins.{$plugin->name}.enabled"] && $plugin instanceof Plugin) {
                if (isset($plugin->features['blueprints'])) {
                    $blueprints["plugin://{$plugin->name}/blueprints"] = $plugin->features['blueprints'];
                }
                if (method_exists($plugin, 'getFormFieldTypes')) {
                    $formFields[$plugin::class] = $plugin->features['formfields'] ?? 0;
                }
            }
        }

        if ($blueprints) {
            // Order by priority.
            arsort($blueprints, SORT_NUMERIC);

            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];
            $locator->addPath('blueprints', '', array_keys($blueprints), ['system', 'blueprints']);
        }

        if ($formFields) {
            // Order by priority.
            arsort($formFields, SORT_NUMERIC);

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
     * @return Plugin[] array of Plugin objects
     * @throws RuntimeException
     */
    public function init()
    {
        if ($this->plugins_initialized)  {
            return $this->items;
        }

        $this->load();

        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        /** @var EventDispatcher $events */
        $events = $grav['events'];

        $loaders = [];
        foreach ($this->items as $key => $instance) {
            // Register only enabled plugins.
            if ($config["plugins.{$instance->name}.enabled"] && $instance instanceof Plugin) {
                // Enabled after the list was built (config changed in between): load it for real now.
                if (str_starts_with((string)$key, self::UNLOADED_PREFIX)) {
                    $name = $instance->name;
                    unset($this->items[$key]);
                    $this->forgetUnloaded($name);
                    $instance = $this->loadPlugin($name);
                    if (!$instance) {
                        continue;
                    }
                    $this->add($instance);
                }
                // Set plugin configuration.
                $instance->setConfig($config);
                // Register autoloader.
                if (method_exists($instance, 'autoload')) {
                    try {
                        $autoloader = $instance->autoload();
                        if ($autoloader instanceof \Composer\Autoload\ClassLoader) {
                            // Composer registers plugin loaders prepended, which forces every
                            // core/vendor class loaded after this point through each plugin's
                            // loader first. Move the plugin loader behind the core one; the
                            // relative order between plugin loaders is preserved.
                            $autoloader->unregister();
                            $autoloader->register(false);
                            $loaders[spl_object_id($autoloader)] = $autoloader;
                        }
                        $instance->setAutoloader($autoloader);
                    } catch (\Throwable $e) {
                        // Log the autoload failure and disable the plugin
                        $grav['log']->error(
                            sprintf("Plugin '%s' autoload failed: %s", $instance->name, $e->getMessage())
                        );

                        // Disable the plugin to prevent further errors
                        $config["plugins.{$instance->name}.enabled"] = false;

                        continue;
                    }
                }
                // Register event listeners.
                $events->addSubscriber($instance);
            }
        }

        // Put one index of the plugin loaders in front of them, so a plugin class is looked up
        // only in the loaders that could hold it instead of in each loader in turn.
        if (count($loaders) > 1) {
            $this->autoloader = new PluginAutoloader($loaders);
            $this->autoloader->register();
        }

        // Plugins Loaded Event
        $event = new PluginsLoadedEvent($grav, $this);
        $grav->dispatchEvent($event);

        $this->plugins_initialized = true;

        return $this->items;
    }

    /**
     * Add a plugin
     *
     * @param Plugin $plugin
     * @return void
     */
    public function add($plugin)
    {
        if (is_object($plugin)) {
            $this->items[$plugin::class] = $plugin;
        }
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        $array = (array)$this;

        unset($array["\0Grav\Common\Iterator\0iteratorUnset"]);

        return $array;
    }

    /**
     * @return Plugin[] Index of all plugins by plugin name.
     */
    public static function getPlugins(): array
    {
        /** @var Plugins $plugins */
        $plugins = Grav::instance()['plugins'];
        $plugins->load();

        $list = [];
        foreach ($plugins as $instance) {
            $list[$instance->name] = $instance;
        }

        return $list;
    }

    /**
     * @param string $name Plugin name
     * @return Plugin|null Plugin object or null if plugin cannot be found.
     */
    public static function getPlugin(string $name)
    {
        $list = static::getPlugins();

        return $list[$name] ?? null;
    }

    /**
     * Return list of all plugin data with their blueprints.
     *
     * @return Data[]
     */
    public static function all()
    {
        $grav = Grav::instance();

        /** @var Plugins $plugins */
        $plugins = $grav['plugins'];
        $plugins->load();
        $list = [];

        foreach ($plugins as $instance) {
            $name = $instance->name;

            try {
                $result = self::get($name);
            } catch (Exception $e) {
                $exception = new RuntimeException(sprintf('Plugin %s: %s', $name, $e->getMessage()), $e->getCode(), $e);

                /** @var Debugger $debugger */
                $debugger = $grav['debugger'];
                $debugger->addMessage("Plugin {$name} cannot be loaded, please check Exceptions tab", 'error');
                $debugger->addException($exception);

                continue;
            }

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

        $obj = new Data((array)$file->content(), $blueprint);

        // Override with user configuration.
        $obj->merge(Grav::instance()['config']->get('plugins.' . $name) ?: []);

        // Save configuration always to user/config.
        $file = CompiledYamlFile::instance("config://plugins/{$name}.yaml");
        $obj->file($file);

        return $obj;
    }

    /**
     * @param string $name
     * @return Plugin|null
     */
    protected function loadPlugin($name)
    {
        // NOTE: ALL THE LOCAL VARIABLES ARE USED INSIDE INCLUDED FILE, DO NOT REMOVE THEM!
        $grav = Grav::instance();
        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $class = null;

        // Start by attempting to load the plugin_name.php file.
        $file = $this->pluginFile($name);
        if ($file) {
            // Local variables available in the file: $grav, $name, $file
            $class = include_once $file;
            if (!is_object($class) || !is_subclass_of($class, Plugin::class, true)) {
                $class = null;
            }
        }

        // If the class hasn't been initialized yet, guess the class name and create a new instance.
        if (null === $class) {
            foreach ($this->pluginClassNames($name) as $pluginClass) {
                if (is_subclass_of($pluginClass, Plugin::class, true)) {
                    $class = new $pluginClass($name, $grav);
                    break;
                }
            }
        }

        // Log a warning if plugin cannot be found.
        if (null === $class) {
            $grav['log']->warning(
                sprintf("Plugin '%s' enabled but not found! Try clearing cache with `bin/grav clearcache`", $name)
            );
        }

        return $class;
    }

    /**
     * @param string $name
     * @return string|null Path of the plugin's main file, or null if it has none.
     */
    private function pluginFile(string $name): ?string
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $file = $locator->findResource('plugins://' . $name . DS . $name . PLUGIN_EXT);

        return $file && is_file($file) ? $file : null;
    }

    /**
     * @param string $name
     * @return string[] Class names a plugin's main class may have, in lookup order.
     */
    private function pluginClassNames(string $name): array
    {
        $className = Inflector::camelize($name);

        return [
            'Grav\\Plugin\\' . ucfirst($name) . 'Plugin',
            'Grav\\Plugin\\' . $className . 'Plugin',
            'Grav\\Plugin\\' . $className,
        ];
    }
}
