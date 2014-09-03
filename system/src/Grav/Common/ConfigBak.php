<?php
namespace Grav\Common;

use Grav\Component\Data\Blueprints;
use Grav\Component\Data\Data;
use Grav\Component\Filesystem\File;
use Grav\Component\Filesystem\Folder;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */
class Config extends Data
{
    /**
     * @var string Configuration location in the disk.
     */
    public $filename;

    /**
     * @var string Path to YAML configuration files.
     */
    public $path;

    /**
     * @var string MD5 from the files.
     */
    public $key;

    /**
     * @var array Configuration file list.
     */
    public $files = array();

    /**
     * @var bool Flag to tell if configuration needs to be saved.
     */
    public $updated = false;


    /**
     * Gets configuration instance.
     *
     * @param  string  $filename
     * @param  string  $path
     * @return static
     */
    public static function instance($filename, $path = SYSTEM_DIR)
    {
        // Load cached version if available..
        if (file_exists($filename)) {
            $data = require_once $filename;

            if (is_array($data) && isset($data['@class']) && $data['@class'] == __CLASS__) {
                $instance = new static($filename, $path, $data);
            }
        }

        // Or initialize new configuration object..
        if (!isset($instance)) {
            $instance = new static($filename, $path);
        }

        // If configuration was updated, store it as cached version.
        /** @var Config $instance */
        if ($instance->updated) {
            $instance->save();
        }

        return $instance;
    }

    /**
     * Constructor.
     * @param string $filename
     * @param string $path
     * @param array $data
     */
    public function __construct($filename, $path, array $data = null)
    {
        $this->filename = (string) $filename;
        $this->path = (string) $path;

        if ($data) {
            $this->key = $data['key'];
            $this->files = $data['files'];
            $this->items = $data['items'];
        }

        $this->reload(false);
        print_r($this->getBlueprintFiles());
        print_r($this->getConfigFiles());
    }

    /**
     * Force reload of the configuration from the disk.
     *
     * @param bool $force
     * @return $this
     */
    public function reload($force = true)
    {
        // Build file map.
        $files = $this->build();
        $key = $this->getKey($files);

        if ($force || $key != $this->key) {
            // First take non-blocking lock to the file.
            File\Php::instance($this->filename)->lock(false);

            // Reset configuration.
            $this->items = array();
            $this->files = array();
            $this->init($files);
            $this->key = $key;
        }

        return $this;
    }

    /**
     * Save configuration into file.
     *
     * Note: Only saves the file if updated flag is set!
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function save()
    {
        // If configuration was updated, store it as cached version.
        try {
            $file = File\Php::instance($this->filename);

            // Only save configuration file if it was successfully locked to prevent multiple saves.
            if ($file->locked() !== false) {
                $file->save($this->toArray());
                $file->unlock();
            }
            $this->updated = false;
        } catch (\Exception $e) {
            // TODO: do not require saving to succeed, but display some kind of error anyway.
            throw new \RuntimeException('Writing configuration to cache folder failed.', 500, $e);
        }

        return $this;
    }

    /**
     * Convert configuration into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            '@class' => get_class($this),
            'key' => $this->key,
            'files' => $this->files,
            'items' => $this->items
        ];
    }

    /**
     * @param $files
     * @return string
     */
    protected function getKey(&$files)
    {
        return md5(serialize($files) . GRAV_VERSION);
    }

    /**
     * Initialize object by loading all the configuration files.
     *
     * @param array $files
     */
    protected function init(array $files)
    {
        $this->updated = true;

        // Then sort the files to have all parent nodes first.
        // This is to make sure that child nodes override parents content.
        uksort(
            $files,
            function($a, $b) {
                $diff = substr_count($a, '/') - substr_count($b, '/');
                return $diff ? $diff : strcmp($a, $b);
            }
        );

        $blueprints = new Blueprints($this->path . '/blueprints/config');

        $items = array();
        foreach ($files as $name => $dummy) {
            $lookup = $this->path . '/config/' . $name . '.yaml';
            $blueprint = $blueprints->get($name);

            $data = new Data(array(), $blueprint);
            if (is_file($lookup)) {
                $data->merge(File\Yaml::instance($lookup)->content());
            }

            // Find the current sub-tree location.
            $current = &$items;
            $parts = explode('/', $name);
            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = array();
                }
                $current = &$current[$part];
            }

            // Handle both updated and deleted configuration files.
            $current = $data->toArray();
        }

        $this->items = $items;
        $this->files = $files;
    }

    /**
     * Build a list of configuration files with their timestamps. Used for loading settings and caching them.
     *
     * @return array
     * @internal
     */
    protected function build()
    {
        // Find all system and user configuration files.
        $options = [
            'compare' => 'Filename',
            'pattern' => '|\.yaml$|',
            'filters' => ['key' => '|\.yaml$|'],
            'key' => 'SubPathname',
            'value' => 'MTime'
        ];

        return Folder::all($this->path . '/config', $options);
    }

    /**
     * Detects all plugins with a configuration file and returns last modification time.
     *
     * @param  string $lookup Location to look up from.
     * @param  string $find  Filename or extension to find.
     * @return array
     */
    protected function detectPlugins($lookup = SYSTEM_DIR, $find = '.yaml')
    {
        if (!is_dir($lookup)) {
            return [];
        }

        $list = [];
        $iterator = new \DirectoryIterator($lookup);
        $path = trim(Folder::getRelativePath($lookup), '/');

        /** @var \DirectoryIterator $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }

            $name = $directory->getBasename();
            $filename = "{$path}/{$name}/" . ($find && $find[0] != '.' ? $find : $name . $find);

            if (is_file($filename)) {
                $list["plugins/{$name}"] = ['file' => $filename, 'mtime' => filemtime($filename)];
            }
        }

        return [$path => $list];
    }

    /**
     * Detects all plugins with a configuration file and returns last modification time.
     *
     * @param  string $lookup Location to look up from.
     * @return array
     */
    protected function detectConfig($lookup = SYSTEM_DIR)
    {
        if (!is_dir($lookup)) {
            return [];
        }

        $path = trim(Folder::getRelativePath($lookup), '/');

        // Find all system and user configuration files.
        $options = [
            'compare' => 'Filename',
            'pattern' => '|\.yaml$|',
            'filters' => [
                'key' => '|\.yaml$|',
                'value' => function (\RecursiveDirectoryIterator $file) use ($path) {
                    return ['file' => "{$path}/{$file->getSubPathname()}", 'mtime' => $file->getMTime()];
                }],
            'key' => 'SubPathname'
        ];

        $list = Folder::all($lookup, $options);
        return [$path => $list];
    }

    public function getBlueprintFiles()
    {
        $list = [];
        $list += $this->detectPlugins(SYSTEM_DIR . 'plugins', 'blueprints.yaml');
        $list += $this->detectConfig(SYSTEM_DIR . 'blueprints/config');
        $list += $this->detectPlugins(PLUGINS_DIR, 'blueprints.yaml');
        $list += $this->detectConfig(USER_DIR . 'blueprints/config');
        return $list;
    }

    public function getConfigFiles()
    {
        $list = [];
        $list += $this->detectPlugins(SYSTEM_DIR . 'plugins');
        $list += $this->detectConfig(SYSTEM_DIR . 'config');
        $list += $this->detectPlugins(PLUGINS_DIR);
        $list += $this->detectConfig(USER_DIR . 'config');

        return $list;
    }
}
