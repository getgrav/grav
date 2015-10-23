<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Themes object holds an array of all the theme objects that Grav knows about.
 *
 * @author RocketTheme
 * @license MIT
 */
class Themes extends Iterator
{
    /** @var Grav */
    protected $grav;

    /** @var Config */
    protected $config;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->config = $grav['config'];

        // Register instance as autoloader for theme inheritance
        spl_autoload_register([$this, 'autoloadTheme']);
    }

    public function init()
    {
        /** @var EventDispatcher $events */
        $events = $this->grav['events'];

        /** @var Themes $themes */
        $themes = $this->grav['themes'];
        $themes->configure();

        try {
            $instance = $themes->load();
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($this->current(). ' theme could not be found');
        }

        if ($instance instanceof EventSubscriberInterface) {
            $events->addSubscriber($instance);
        }

        $this->grav['theme'] = $instance;
    }

    /**
     * Return list of all theme data with their blueprints.
     *
     * @return array
     */
    public function all()
    {
        $list = array();
        $locator = Grav::instance()['locator'];

        $themes = (array) $locator->findResources('themes://', false);
        foreach ($themes as $path) {
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

    /**
     * Get theme configuration or throw exception if it cannot be found.
     *
     * @param  string            $name
     * @return Data
     * @throws \RuntimeException
     */
    public function get($name)
    {
        if (!$name) {
            throw new \RuntimeException('Theme name not provided.');
        }

        $blueprints = new Blueprints('themes://');
        $blueprint = $blueprints->get("{$name}/blueprints");
        $blueprint->name = $name;

        // Find thumbnail.
        $thumb = "themes://{$name}/thumbnail.jpg";
        if ($path = $this->grav['locator']->findResource($thumb, false)) {
            $blueprint->set('thumbnail', $this->grav['base_url'] . '/' . $path);
        }

        // Load default configuration.
        $file = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT);
        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $obj->merge($this->grav['config']->get('themes.' . $name) ?: []);

        // Save configuration always to user/config.
        $file = CompiledYamlFile::instance("config://themes/{$name}" . YAML_EXT);
        $obj->file($file);

        return $obj;
    }

    /**
     * Return name of the current theme.
     *
     * @return string
     */
    public function current()
    {
        return (string) $this->config->get('system.pages.theme');
    }

    /**
     * Load current theme.
     *
     * @return Theme|object
     */
    public function load()
    {
        // NOTE: ALL THE LOCAL VARIABLES ARE USED INSIDE INCLUDED FILE, DO NOT REMOVE THEM!
        $grav = $this->grav;
        $config = $this->config;
        $name = $this->current();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $file = $locator('theme://theme.php') ?: $locator("theme://{$name}.php");

        $inflector = $grav['inflector'];

        if ($file) {
            // Local variables available in the file: $grav, $config, $name, $file
            $class = include $file;

            if (!is_object($class)) {
                $themeClassFormat = [
                    'Grav\\Theme\\'.ucfirst($name),
                    'Grav\\Theme\\'.$inflector->camelize($name)
                ];
                $themeClassName = false;

                foreach ($themeClassFormat as $themeClass) {
                    if (class_exists($themeClass)) {
                        $themeClassName = $themeClass;
                        $class = new $themeClassName($grav, $config, $name);
                        break;
                    }
                }
            }
        } elseif (!$locator('theme://') && !defined('GRAV_CLI')) {
            exit("Theme '$name' does not exist, unable to display page.");
        }

        if (empty($class)) {
            $class = new Theme($grav, $config, $name);
        }

        return $class;
    }

    /**
     * Configure and prepare streams for current template.
     *
     * @throws \InvalidArgumentException
     */
    public function configure()
    {
        $name = $this->current();
        $config = $this->config;

        $this->loadConfiguration($name, $config);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $registered = stream_get_wrappers();
        $schemes = $config->get(
            "themes.{$name}.streams.schemes",
            ['theme' => ['paths' => $locator->findResources("themes://{$name}", false)]]
        );

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }

            if (in_array($scheme, $registered)) {
                stream_wrapper_unregister($scheme);
            }
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new \InvalidArgumentException("Stream '{$type}' could not be initialized.");
            }
        }

        // Load languages after streams has been properly initialized
        $this->loadLanguages($this->config);
    }

    /**
     * Load theme configuration.
     *
     * @param string  $name    Theme name
     * @param Config  $config  Configuration class
     */
    protected function loadConfiguration($name, Config $config)
    {
        $themeConfig = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT)->content();
        $config->joinDefaults("themes.{$name}", $themeConfig);
    }

    /**
     * Load theme languages.
     *
     * @param Config  $config  Configuration class
     */
    protected function loadLanguages(Config $config)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($config->get('system.languages.translations', true)) {
            $languageFiles = array_reverse($locator->findResources("theme://languages" . YAML_EXT));

            $languages = [];
            foreach ($languageFiles as $language) {
                $languages[] = CompiledYamlFile::instance($language)->content();
            }

            if ($languages) {
                $languages = call_user_func_array('array_replace_recursive', $languages);
                $config->getLanguages()->mergeRecursive($languages);
            }
        }
    }

    /**
     * Autoload theme classes for inheritance
     *
     * @param  string $class Class name
     *
     * @return mixed  false  FALSE if unable to load $class; Class name if
     *                       $class is successfully loaded
     */
    protected function autoloadTheme($class)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $prefix = "Grav\\Theme";
        if (false !== strpos($class, $prefix)) {
            // Remove prefix from class
            $class = substr($class, strlen($prefix));

            // Replace namespace tokens to directory separators
            $path = ltrim(preg_replace('#\\\|_(?!.+\\\)#', '/', $class), '/');
            $file = $locator->findResource("themes://{$path}/{$path}.php");

            // Load class
            if (stream_resolve_include_path($file)) {
              return include_once($file);
            }
        }

        return false;
    }
}
