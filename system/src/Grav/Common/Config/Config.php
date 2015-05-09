<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Blueprints\Blueprints;
use RocketTheme\Toolbox\File\PhpFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */
class Config extends Data
{
    protected $grav;
    protected $streams = [
        'system' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['system'],
            ]
        ],
        'user' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user'],
            ]
        ],
        'blueprints' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://blueprints', 'system/blueprints'],
            ]
        ],
        'config' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://config', 'system/config'],
            ]
        ],
        'plugins' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://plugins'],
             ]
        ],
        'plugin' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://plugins'],
            ]
        ],
        'themes' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://themes'],
            ]
        ],
        'cache' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['cache'],
                'images' => ['images']
            ]
        ],
        'log' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['logs']
            ]
        ],
        'backup' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['backup']
            ]
        ]
    ];

    protected $setup = [];

    protected $blueprintFiles = [];
    protected $configFiles = [];
    protected $checksum;
    protected $timestamp;

    protected $configLookup;
    protected $blueprintLookup;
    protected $pluginLookup;

    protected $finder;
    protected $environment;
    protected $messages = [];

    public function __construct(array $setup = array(), Grav $grav = null, $environment = null)
    {
        $this->grav = $grav ?: Grav::instance();
        $this->finder = new ConfigFinder;
        $this->environment = $environment ?: 'localhost';
        $this->messages[] = 'Environment Name: ' . $this->environment;

        // Make sure that
        if (!isset($setup['streams']['schemes'])) {
            $setup['streams']['schemes'] = [];
        }
        $setup['streams']['schemes'] += $this->streams;

        $setup = $this->autoDetectEnvironmentConfig($setup);
        $this->messages[] = $setup['streams']['schemes']['config']['prefixes'][''];

        $this->setup = $setup;
        parent::__construct($setup);

        $this->check();
    }

    public function key()
    {
        return $this->checksum();
    }

    public function reload()
    {
        $this->items = $this->setup;
        $this->check();
        $this->init();
        $this->debug();

        return $this;
    }

    protected function check()
    {
        $streams = isset($this->items['streams']['schemes']) ? $this->items['streams']['schemes'] : null;
        if (!is_array($streams)) {
            throw new \InvalidArgumentException('Configuration is missing streams.schemes!');
        }
        $diff = array_keys(array_diff_key($this->streams, $streams));
        if ($diff) {
            throw new \InvalidArgumentException(
                sprintf('Configuration is missing keys %s from streams.schemes!', implode(', ', $diff))
            );
        }
    }

    public function debug()
    {
        foreach ($this->messages as $message) {
            $this->grav['debugger']->addMessage($message);
        }
        $this->messages = [];
    }

    public function init()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $this->configLookup = $locator->findResources('config://');
        $this->blueprintLookup = $locator->findResources('blueprints://config');
        $this->pluginLookup = $locator->findResources('plugins://');

        $this->loadCompiledBlueprints($this->blueprintLookup, $this->pluginLookup, 'master');
        $this->loadCompiledConfig($this->configLookup, $this->pluginLookup, 'master');

        $this->initializeLocator($locator);
    }

    public function checksum()
    {
        $checkBlueprints = $this->get('system.cache.check.blueprints', false);
        $checkConfig = $this->get('system.cache.check.config', true);
        $checkSystem = $this->get('system.cache.check.system', true);

        if (!$checkBlueprints && !$checkConfig && !$checkSystem) {
            $this->messages[] = 'Skip configuration timestamp check.';
            return false;
        }

        // Generate checksum according to the configuration settings.
        if (!$checkConfig) {
            $this->messages[] = 'Check configuration timestamps from system.yaml files.';
            // Just check changes in system.yaml files and ignore all the other files.
            $cc = $checkSystem ? $this->finder->locateConfigFile($this->configLookup, 'system') : [];
        } else {
            $this->messages[] = 'Check configuration timestamps from all configuration files.';
            // Check changes in all configuration files.
            $cc = $this->finder->locateConfigFiles($this->configLookup, $this->pluginLookup);
        }

        if ($checkBlueprints) {
            $this->messages[] = 'Check blueprint timestamps from all blueprint files.';
            $cb = $this->finder->locateBlueprintFiles($this->blueprintLookup, $this->pluginLookup);
        } else {
            $cb = [];
        }

        return md5(json_encode([$cc, $cb]));
    }

    protected function autoDetectEnvironmentConfig($items)
    {
        $environment = $this->environment;
        $env_stream = 'user://'.$environment.'/config';

        if (file_exists(USER_DIR.$environment.'/config')) {
            array_unshift($items['streams']['schemes']['config']['prefixes'][''], $env_stream);
        }

        return $items;
    }

    protected function loadCompiledBlueprints($blueprints, $plugins, $filename = null)
    {
        $checksum = md5(json_encode($blueprints));
        $filename = $filename
            ? CACHE_DIR . 'compiled/blueprints/' . $filename . '-' . $this->environment . '.php'
            : CACHE_DIR . 'compiled/blueprints/' . $checksum . '-' . $this->environment . '.php';
        $file = PhpFile::instance($filename);
        $cache = $file->exists() ? $file->content() : null;
        $blueprintFiles = $this->finder->locateBlueprintFiles($blueprints, $plugins);
        $checksum .= ':'.md5(json_encode($blueprintFiles));
        $class = get_class($this);

        // Load real file if cache isn't up to date (or is invalid).
        if (
            !is_array($cache)
            || !isset($cache['checksum'])
            || !isset($cache['@class'])
            || $cache['checksum'] != $checksum
            || $cache['@class'] != $class
        ) {
            // Attempt to lock the file for writing.
            $file->lock(false);

            // Load blueprints.
            $this->blueprints = new Blueprints;
            foreach ($blueprintFiles as $files) {
                $this->loadBlueprintFiles($files);
            }

            $cache = [
                '@class' => $class,
                'checksum' => $checksum,
                'files' => $blueprintFiles,
                'data' => $this->blueprints->toArray()
            ];
            // If compiled file wasn't already locked by another process, save it.
            if ($file->locked() !== false) {
                $this->messages[] = 'Saving compiled blueprints.';
                $file->save($cache);
                $file->unlock();
            }
        } else {
            $this->blueprints = new Blueprints($cache['data']);
        }
    }

    protected function loadCompiledConfig($configs, $plugins, $filename = null)
    {
        $filename = $filename
            ? CACHE_DIR . 'compiled/config/' . $filename . '-' . $this->environment . '.php'
            : CACHE_DIR . 'compiled/config/' . $checksum . '-' . $this->environment . '.php';
        $file = PhpFile::instance($filename);
        $cache = $file->exists() ? $file->content() : null;
        $class = get_class($this);
        $checksum = $this->checksum();

        if (
            !is_array($cache)
            || !isset($cache['checksum'])
            || !isset($cache['@class'])
            || $cache['@class'] != $class
        ) {
            $this->messages[] = 'No cached configuration, compiling new configuration..';
        } else if ($cache['checksum'] !== $checksum) {
            $this->messages[] = 'Configuration checksum mismatch, reloading configuration..';
        } else {
            $this->messages[] = 'Configuration checksum matches, using cached version.';

            $this->items = $cache['data'];
            return;
        }

        $configFiles = $this->finder->locateConfigFiles($configs, $plugins);

        // Attempt to lock the file for writing.
        $file->lock(false);

        // Load configuration.
        foreach ($configFiles as $files) {
            $this->loadConfigFiles($files);
        }
        $cache = [
            '@class' => $class,
            'timestamp' => time(),
            'checksum' => $checksum,
            'data' => $this->toArray()
        ];

        // If compiled file wasn't already locked by another process, save it.
        if ($file->locked() !== false) {
            $this->messages[] = 'Saving compiled configuration.';
            $file->save($cache);
            $file->unlock();
        }

        $this->items = $cache['data'];
    }

    /**
     * Load blueprints.
     *
     * @param array  $files
     */
    public function loadBlueprintFiles(array $files)
    {
        foreach ($files as $name => $item) {
            $file = CompiledYamlFile::instance($item['file']);
            $this->blueprints->embed($name, $file->content(), '/');
        }
    }

    /**
     * Load configuration.
     *
     * @param array  $files
     */
    public function loadConfigFiles(array $files)
    {
        foreach ($files as $name => $item) {
            $file = CompiledYamlFile::instance($item['file']);
            $this->join($name, $file->content(), '/');
        }
    }

    /**
     * Initialize resource locator by using the configuration.
     *
     * @param UniformResourceLocator $locator
     */
    public function initializeLocator(UniformResourceLocator $locator)
    {
        $locator->reset();

        $schemes = (array) $this->get('streams.schemes', []);

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }
        }
    }

    /**
     * Get available streams and their types from the configuration.
     *
     * @return array
     */
    public function getStreams()
    {
        $schemes = [];
        foreach ((array) $this->get('streams.schemes') as $scheme => $config) {
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            $schemes[$scheme] = $type;
        }

        return $schemes;
    }
}
