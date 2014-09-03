<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYaml;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Uri;
use Grav\Component\Blueprints\Blueprints;
use Grav\Component\Data\Data;
use Grav\Component\Filesystem\Folder;
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
        'blueprints' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '/' => ['user/blueprints', 'system/blueprints'],
            ]
        ],
        'config' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '/' => ['user/config', 'system/config'],
            ]
        ],
        'plugin' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user/plugins'],
             ]
        ],
        'theme' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '/' => ['user/themes'],
            ]
        ],
        'cache' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['cache']
            ]
        ],
        'log' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['logs']
            ]
        ]
    ];

    protected $blueprintFiles = [];
    protected $configFiles = [];
    protected $checksum;
    protected $timestamp;

    protected $configLookup;
    protected $blueprintLookup;
    protected $pluginLookup;


    public function __construct(array $items = array(), Grav $grav = null)
    {
        $this->grav = $grav ?: Grav::instance();

        if (isset($items['@class'])) {
            if ($items['@class'] != get_class($this)) {
                throw new \InvalidArgumentException('Unrecognized config cache file!');
            }
            // Loading pre-compiled configuration.
            $this->timestamp = (int) $items['timestamp'];
            $this->checksum = (string) $items['checksum'];
            $this->items = (array) $items['data'];
        } else {
            // Make sure that
            if (!isset($items['streams']['schemes'])) {
                $items['streams']['schemes'] = [];
            }
            $items['streams']['schemes'] += $this->streams;

            parent::__construct($items);
        }
        $this->check();
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

    public function init()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $this->configLookup = $locator->findResources('config:///');
        $this->blueprintLookup = $locator->findResources('blueprints:///config');
        $this->pluginLookup = $locator->findResources('plugin:///');

        $checksum = $this->checksum();
        if ($checksum == $this->checksum) {
            return;
        }

        $this->checksum = $checksum;

        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        // If not set, add manually current base url.
        $this->def('system.base_url_absolute', $uri->rootUrl(true));
        $this->def('system.base_url_relative', $uri->rootUrl(false));

        $this->loadCompiledBlueprints($this->blueprintLookup, $this->pluginLookup, 'master');
        $this->loadCompiledConfig($this->configLookup, $this->pluginLookup, 'master');
    }

    public function checksum()
    {
        $checkBlueprints = $this->get('system.cache.check.blueprints', true);
        $checkConfig = $this->get('system.cache.check.config', true);
        $checkSystem = $this->get('system.cache.check.system', true);

        if (!$checkBlueprints && !$checkConfig && !$checkSystem) {
            return false;
        }

        // Generate checksum according to the configuration settings.
        if (!$checkConfig) {
            // Just check changes in system.yaml files and ignore all the other files.
            $cc = $checkSystem ? $this->detectFile($this->configLookup, 'system') : [];
        } else {
            // Check changes in all configuration files.
            $cc = $this->getConfigFiles($this->configLookup, $this->pluginLookup);
        }
        $cb = $checkBlueprints ? $this->getBlueprintFiles($this->blueprintLookup, $this->pluginLookup) : [];

        return md5(serialize([$cc, $cb]));
    }

    protected function loadCompiledBlueprints($blueprints, $plugins, $filename = null)
    {
        $checksum = md5(serialize($blueprints));
        $filename = $filename
            ? CACHE_DIR . 'compiled/blueprints/' . $filename .'.php'
            : CACHE_DIR . 'compiled/blueprints/' . $checksum .'.php';
        $file = PhpFile::instance($filename);

        if ($file->exists()) {
            $cache = $file->exists() ? $file->content() : null;
        } else {
            $cache = null;
        }

        $blueprintFiles = $this->getBlueprintFiles($blueprints, $plugins);
        $checksum .= ':'.md5(serialize($blueprintFiles));
        $class = get_class($this);

        // Load real file if cache isn't up to date (or is invalid).
        if (
            !is_array($cache)
            || empty($cache['checksum'])
            || empty($cache['$class'])
            || $cache['checksum'] != $checksum
            || $cache['@class'] != $class
        ) {
            // Attempt to lock the file for writing.
            $file->lock(false);

            // Load blueprints.
            $this->blueprints = new Blueprints();
            foreach ($blueprintFiles as $key => $files) {
                $this->loadBlueprints($key);
            }

            $cache = [
                '@class' => $class,
                'checksum' => $checksum,
                'files' => $blueprintFiles,
                'data' => $this->blueprints->toArray()
            ];

            // If compiled file wasn't already locked by another process, save it.
            if ($file->locked() !== false) {
                $file->save($cache);
                $file->unlock();
            }
        } else {
            $this->blueprints = new Blueprints($cache['data']);
        }
    }

    protected function loadCompiledConfig($configs, $plugins, $filename = null)
    {
        $checksum = md5(serialize($configs));
        $filename = $filename
            ? CACHE_DIR . 'compiled/config/' . $filename .'.php'
            : CACHE_DIR . 'compiled/config/' . $checksum .'.php';
        $file = PhpFile::instance($filename);

        if ($file->exists()) {
            $cache = $file->exists() ? $file->content() : null;
        } else {
            $cache = null;
        }

        $configFiles = $this->getConfigFiles($configs, $plugins);
        $checksum .= ':'.md5(serialize($configFiles));
        $class = get_class($this);

        // Load real file if cache isn't up to date (or is invalid).
        if (
            !is_array($cache)
            || $cache['checksum'] != $checksum
            || $cache['@class'] != $class
        ) {
            // Attempt to lock the file for writing.
            $file->lock(false);

            // Load configuration.
            foreach ($configFiles as $key => $files) {
                $this->loadConfig($key);
            }
            $cache = [
                '@class' => $class,
                'timestamp' => time(),
                'checksum' => $this->checksum,
                'data' => $this->toArray()
            ];

            // If compiled file wasn't already locked by another process, save it.
            if ($file->locked() !== false) {
                $file->save($cache);
                $file->unlock();
            }
        }

        $this->items = $cache['data'];
    }

    /**
     * Load global blueprints.
     *
     * @param string $key
     * @param array $files
     */
    public function loadBlueprints($key, array $files = null)
    {
        if (is_null($files)) {
            $files = $this->blueprintFiles[$key];
        }
        foreach ($files as $name => $item) {
            $file = CompiledYaml::instance($item['file']);
            $this->blueprints->embed($name, $file->content(), '/');
        }
    }

    /**
     * Load global configuration.
     *
     * @param string $key
     * @param array $files
     */
    public function loadConfig($key, array $files = null)
    {
        if (is_null($files)) {
            $files = $this->configFiles[$key];
        }
        foreach ($files as $name => $item) {
            $file = CompiledYaml::instance($item['file']);
            $this->join($name, $file->content(), '/');
        }
    }

    /**
     * Get all blueprint files (including plugins).
     *
     * @param array $blueprints
     * @param array $plugins
     * @return array
     */
    protected function getBlueprintFiles(array $blueprints, array $plugins)
    {
        $list = [];
        foreach (array_reverse($plugins) as $folder) {
            $list += $this->detectPlugins($folder, true);
        }
        foreach (array_reverse($blueprints) as $folder) {
            $list += $this->detectConfig($folder, true);
        }
        return $list;
    }

    /**
     * Get all configuration files.
     *
     * @param array $configs
     * @param array $plugins
     * @return array
     */
    protected function getConfigFiles(array $configs, array $plugins)
    {
        $list = [];
        foreach (array_reverse($plugins) as $folder) {
            $list += $this->detectPlugins($folder);
        }
        foreach (array_reverse($configs) as $folder) {
            $list += $this->detectConfig($folder);
        }
        return $list;
    }

    /**
     * Detects all plugins with a configuration file and returns last modification time.
     *
     * @param  string $lookup Location to look up from.
     * @param  bool $blueprints
     * @return array
     * @internal
     */
    protected function detectPlugins($lookup = SYSTEM_DIR, $blueprints = false)
    {
        $find = $blueprints ? 'blueprints.yaml' : '.yaml';
        $location = $blueprints ? 'blueprintFiles' : 'configFiles';
        $path = trim(Folder::getRelativePath($lookup), '/');
        if (isset($this->{$location}[$path])) {
            return [$path => $this->{$location}[$path]];
        }

        $list = [];

        if (is_dir($lookup)) {
            $iterator = new \DirectoryIterator($lookup);

            /** @var \DirectoryIterator $directory */
            foreach ($iterator as $directory) {
                if (!$directory->isDir() || $directory->isDot()) {
                    continue;
                }

                $name = $directory->getBasename();
                $filename = "{$path}/{$name}/" . ($find && $find[0] != '.' ? $find : $name . $find);

                if (is_file($filename)) {
                    $list["plugins/{$name}"] = ['file' => $filename, 'modified' => filemtime($filename)];
                }
            }
        }

        $this->{$location}[$path] = $list;

        return [$path => $list];
    }

    /**
     * Detects all plugins with a configuration file and returns last modification time.
     *
     * @param  string $lookup Location to look up from.
     * @param  bool $blueprints
     * @return array
     * @internal
     */
    protected function detectConfig($lookup = SYSTEM_DIR, $blueprints = false)
    {
        $location = $blueprints ? 'blueprintFiles' : 'configFiles';
        $path = trim(Folder::getRelativePath($lookup), '/');
        if (isset($this->{$location}[$path])) {
            return [$path => $this->{$location}[$path]];
        }

        if (is_dir($lookup)) {
            // Find all system and user configuration files.
            $options = [
                'compare' => 'Filename',
                'pattern' => '|\.yaml$|',
                'filters' => [
                    'key' => '|\.yaml$|',
                    'value' => function (\RecursiveDirectoryIterator $file) use ($path) {
                        return ['file' => "{$path}/{$file->getSubPathname()}", 'modified' => $file->getMTime()];
                    }],
                'key' => 'SubPathname'
            ];

            $list = Folder::all($lookup, $options);
        } else {
            $list = [];
        }

        $this->{$location}[$path] = $list;

        return [$path => $list];
    }

    /**
     * Detects all instances of the file and returns last modification time.
     *
     * @param  array $lookups Locations to look up from.
     * @param  string $name
     * @return array
     * @internal
     */
    protected function detectFile(array $lookups, $name)
    {
        $list = [];
        $filename = "{$name}.yaml";
        foreach ($lookups as $lookup) {
            $path = trim(Folder::getRelativePath($lookup), '/');

            if (is_file("{$lookup}/{$filename}")) {
                $modified = filemtime("{$lookup}/{$filename}");
            } else {
                $modified = 0;
            }
            $list[$path] = [$name => ['file' => "{$path}/{$filename}", 'modified' => $modified]];
        }

        return $list;
    }
}
