<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Themes extends Iterator
{
    /** @var Grav */
    protected $grav;

    /** @var Config */
    protected $config;

    protected $inited = false;

    /**
     * Themes constructor.
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        parent::__construct();

        $this->grav = $grav;
        $this->config = $grav['config'];

        // Register instance as autoloader for theme inheritance
        spl_autoload_register([$this, 'autoloadTheme']);
    }

    public function init()
    {
        /** @var Themes $themes */
        $themes = $this->grav['themes'];
        $themes->configure();

        $this->initTheme();
    }

    public function initTheme()
    {
        if ($this->inited === false) {
            /** @var Themes $themes */
            $themes = $this->grav['themes'];

            try {
                $instance = $themes->load();
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException($this->current() . ' theme could not be found');
            }

            if ($instance instanceof EventSubscriberInterface) {
                /** @var EventDispatcher $events */
                $events = $this->grav['events'];

                $events->addSubscriber($instance);
            }

            $this->grav['theme'] = $instance;

            $this->grav->fireEvent('onThemeInitialized');

            $this->inited = true;
        }
    }

    /**
     * Return list of all theme data with their blueprints.
     *
     * @return array
     */
    public function all()
    {
        $list = [];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $iterator = $locator->getIterator('themes://');

        /** @var \DirectoryIterator $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }

            $theme = $directory->getBasename();
            $result = self::get($theme);

            if ($result) {
                $list[$theme] = $result;
            }
        }
        ksort($list);

        return $list;
    }

    /**
     * Get theme configuration or throw exception if it cannot be found.
     *
     * @param  string $name
     *
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

        // Load default configuration.
        $file = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT);

        // ensure this is a valid theme
        if (!$file->exists()) {
            return null;
        }

        // Find thumbnail.
        $thumb = "themes://{$name}/thumbnail.jpg";
        $path = $this->grav['locator']->findResource($thumb, false);

        if ($path) {
            $blueprint->set('thumbnail', $this->grav['base_url'] . '/' . $path);
        }

        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $obj->merge($this->config->get('themes.' . $name) ?: []);

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
        return (string)$this->config->get('system.pages.theme');
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
                    'Grav\\Theme\\' . ucfirst($name),
                    'Grav\\Theme\\' . $inflector->camelize($name)
                ];

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

        $this->config->set('theme', $config->get('themes.' . $name));

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

        $schemes = $config->get("themes.{$name}.streams.schemes", []);
        $schemes += [
            'theme' => [
                'type' => 'ReadOnlyStream',
                'paths' => $locator->findResources("themes://{$name}", false)
            ]
        ];

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
     * @param string $name   Theme name
     * @param Config $config Configuration class
     */
    protected function loadConfiguration($name, Config $config)
    {
        $themeConfig = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT)->content();
        $config->joinDefaults("themes.{$name}", $themeConfig);
    }

    /**
     * Load theme languages.
     *
     * @param Config $config Configuration class
     */
    protected function loadLanguages(Config $config)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($config->get('system.languages.translations', true)) {
            $language_file = $locator->findResource("theme://languages" . YAML_EXT);
            if ($language_file) {
                $language = CompiledYamlFile::instance($language_file)->content();
                $this->grav['languages']->mergeRecursive($language);
            }
            $languages_folder = $locator->findResource("theme://languages/");
            if (file_exists($languages_folder)) {
                $languages = [];
                $iterator = new \DirectoryIterator($languages_folder);

                /** @var \DirectoryIterator $directory */
                foreach ($iterator as $file) {
                    if ($file->getExtension() != 'yaml') {
                        continue;
                    }
                    $languages[$file->getBasename('.yaml')] = CompiledYamlFile::instance($file->getPathname())->content();
                }
                $this->grav['languages']->mergeRecursive($languages);
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
        $prefix = "Grav\\Theme";
        if (false !== strpos($class, $prefix)) {
            // Remove prefix from class
            $class = substr($class, strlen($prefix));

            // Try Old style theme classes
            $path = strtolower(ltrim(preg_replace('#\\\|_(?!.+\\\)#', '/', $class), '/'));
            $file = $this->grav['locator']->findResource("themes://{$path}/{$path}.php");

            // Load class
            if (file_exists($file)) {
                return include_once($file);
            }

            // Replace namespace tokens to directory separators
            $path = $this->grav['inflector']->hyphenize(ltrim($class,"\\"));
            $file = $this->grav['locator']->findResource("themes://{$path}/{$path}.php");

            // Load class
            if (file_exists($file)) {
                return include_once($file);
            }
        }

        return false;
    }
}
