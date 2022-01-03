<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use DirectoryIterator;
use Exception;
use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Framework\Psr7\Response;
use InvalidArgumentException;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function defined;
use function in_array;
use function strlen;

/**
 * Class Themes
 * @package Grav\Common
 */
class Themes extends Iterator
{
    /** @var Grav */
    protected $grav;
    /** @var Config */
    protected $config;
    /** @var bool */
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

    /**
     * @return void
     */
    public function init()
    {
        /** @var Themes $themes */
        $themes = $this->grav['themes'];
        $themes->configure();

        $this->initTheme();
    }

    /**
     * @return void
     */
    public function initTheme()
    {
        if ($this->inited === false) {
            /** @var Themes $themes */
            $themes = $this->grav['themes'];

            try {
                $instance = $themes->load();
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException($this->current() . ' theme could not be found');
            }

            // Register autoloader.
            if (method_exists($instance, 'autoload')) {
                $instance->autoload();
            }

            // Register event listeners.
            if ($instance instanceof EventSubscriberInterface) {
                /** @var EventDispatcher $events */
                $events = $this->grav['events'];
                $events->addSubscriber($instance);
            }

            // Register blueprints.
            if (is_dir('theme://blueprints/pages')) {
                /** @var UniformResourceLocator $locator */
                $locator = $this->grav['locator'];
                $locator->addPath('blueprints', '', ['theme://blueprints'], ['user', 'blueprints']);
            }

            // Register form fields.
            if (method_exists($instance, 'getFormFieldTypes')) {
                /** @var Plugins $plugins */
                $plugins = $this->grav['plugins'];
                $plugins->formFieldTypes = $instance->getFormFieldTypes() + $plugins->formFieldTypes;
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

        /** @var DirectoryIterator $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }

            $theme = $directory->getFilename();

            try {
                $result = $this->get($theme);
            } catch (Exception $e) {
                $exception = new RuntimeException(sprintf('Theme %s: %s', $theme, $e->getMessage()), $e->getCode(), $e);

                /** @var Debugger $debugger */
                $debugger = $this->grav['debugger'];
                $debugger->addMessage("Theme {$theme} cannot be loaded, please check Exceptions tab", 'error');
                $debugger->addException($exception);

                continue;
            }

            if ($result) {
                $list[$theme] = $result;
            }
        }
        ksort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * Get theme configuration or throw exception if it cannot be found.
     *
     * @param  string $name
     * @return Data|null
     * @throws RuntimeException
     */
    public function get($name)
    {
        if (!$name) {
            throw new RuntimeException('Theme name not provided.');
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

        $obj = new Data((array)$file->content(), $blueprint);

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
     * @return Theme
     */
    public function load()
    {
        // NOTE: ALL THE LOCAL VARIABLES ARE USED INSIDE INCLUDED FILE, DO NOT REMOVE THEM!
        $grav = $this->grav;
        $config = $this->config;
        $name = $this->current();
        $class = null;

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        // Start by attempting to load the theme.php file.
        $file = $locator('theme://theme.php') ?: $locator("theme://{$name}.php");
        if ($file) {
            // Local variables available in the file: $grav, $config, $name, $file
            $class = include $file;
            if (!\is_object($class) || !is_subclass_of($class, Theme::class, true)) {
                $class = null;
            }
        } elseif (!$locator('theme://') && !defined('GRAV_CLI')) {
            $response = new Response(500, [], "Theme '$name' does not exist, unable to display page.");

            $grav->close($response);
        }

        // If the class hasn't been initialized yet, guess the class name and create a new instance.
        if (null === $class) {
            $themeClassFormat = [
                'Grav\\Theme\\' . Inflector::camelize($name),
                'Grav\\Theme\\' . ucfirst($name)
            ];

            foreach ($themeClassFormat as $themeClass) {
                if (is_subclass_of($themeClass, Theme::class, true)) {
                    $class = new $themeClass($grav, $config, $name);
                    break;
                }
            }
        }

        // Finally if everything else fails, just create a new instance from the default Theme class.
        if (null === $class) {
            $class = new Theme($grav, $config, $name);
        }

        $this->config->set('theme', $config->get('themes.' . $name));

        return $class;
    }

    /**
     * Configure and prepare streams for current template.
     *
     * @return void
     * @throws InvalidArgumentException
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

            if (in_array($scheme, $registered, true)) {
                stream_wrapper_unregister($scheme);
            }
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] !== '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new InvalidArgumentException("Stream '{$type}' could not be initialized.");
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
     * @return void
     */
    protected function loadConfiguration($name, Config $config)
    {
        $themeConfig = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT)->content();
        $config->joinDefaults("themes.{$name}", $themeConfig);
    }

    /**
     * Load theme languages.
     * Reads ALL language files from theme stream and merges them.
     *
     * @param Config $config Configuration class
     * @return void
     */
    protected function loadLanguages(Config $config)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($config->get('system.languages.translations', true)) {
            $language_files = array_reverse($locator->findResources('theme://languages' . YAML_EXT));
            foreach ($language_files as $language_file) {
                $language = CompiledYamlFile::instance($language_file)->content();
                $this->grav['languages']->mergeRecursive($language);
            }
            $languages_folders = array_reverse($locator->findResources('theme://languages'));
            foreach ($languages_folders as $languages_folder) {
                $languages = [];
                $iterator = new DirectoryIterator($languages_folder);
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'yaml') {
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
     * @return mixed|false   FALSE if unable to load $class; Class name if
     *                       $class is successfully loaded
     */
    protected function autoloadTheme($class)
    {
        $prefix = 'Grav\\Theme\\';
        if (false !== strpos($class, $prefix)) {
            // Remove prefix from class
            $class = substr($class, strlen($prefix));
            $locator = $this->grav['locator'];

            // First try lowercase version of the classname.
            $path = strtolower($class);
            $file = $locator("themes://{$path}/theme.php") ?: $locator("themes://{$path}/{$path}.php");

            if ($file) {
                return include_once $file;
            }

            // Replace namespace tokens to directory separators
            $path = $this->grav['inflector']->hyphenize($class);
            $file = $locator("themes://{$path}/theme.php") ?: $locator("themes://{$path}/{$path}.php");

            // Load class
            if ($file) {
                return include_once $file;
            }

            // Try Old style theme classes
            $path = preg_replace('#\\\|_(?!.+\\\)#', '/', $class);
            \assert(null !== $path);

            $path = strtolower($path);
            $file = $locator("themes://{$path}/theme.php") ?: $locator("themes://{$path}/{$path}.php");

            // Load class
            if ($file) {
                return include_once $file;
            }
        }

        return false;
    }
}
