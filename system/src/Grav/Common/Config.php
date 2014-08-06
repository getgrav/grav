<?php
namespace Grav\Common;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\Filesystem\File;
use Grav\Common\Filesystem\Folder;

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
    public $issues = array();

    /**
     * Constructor.
     */
    public function __construct($filename)
    {
        $this->filename = realpath(dirname($filename)) . '/' . basename($filename);

        $this->reload(false);
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
        $key = md5(serialize($files) . GRAV_VERSION);

        if ($force || $key != $this->key) {
            // First take non-blocking lock to the file.
            File\Config::instance($this->filename)->lock(false);

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
            $file = File\Config::instance($this->filename);

            // Only save configuration file if it wasn't locked. Also invalidate opcache after saving.
            // This prevents us from saving the file multiple times in a row and gives faster recovery.
            if ($file->locked() !== false) {
                $file->save($this);
                $file->unlock();
            }
            $this->updated = false;
        } catch (\Exception $e) {
            $this->issues[] = 'Writing configuration into cache failed.';
            //throw new \RuntimeException('Writing configuration into cache failed.', 500, $e);
        }

        return $this;
    }

    /**
     * Gets configuration instance.
     *
     * @param  string  $filename
     * @return \Grav\Common\Config
     */
    public static function instance($filename)
    {
        // Load cached version if available..
        if (file_exists($filename)) {
            clearstatcache(true, $filename);
            require_once $filename;

            if (class_exists('\Grav\Config')) {
                $instance = new \Grav\Config($filename);
            }
        }

        // Or initialize new configuration object..
        if (!isset($instance)) {
            $instance = new static($filename);
        }

        // If configuration was updated, store it as cached version.
        if ($instance->updated) {
            $instance->save();
        }


        // If not set, add manually current base url.
        if (empty($instance->items['system']['base_url_absolute'])) {
            $instance->items['system']['base_url_absolute'] = Registry::get('Uri')->rootUrl(true);
        }

        if (empty($instance->items['system']['base_url_relative'])) {
            $instance->items['system']['base_url_relative'] = Registry::get('Uri')->rootUrl(false);
        }

        return $instance;
    }

    /**
     * Convert configuration into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return array('key' => $this->key, 'files' => $this->files, 'items' => $this->items);
    }

    /**
     * Initialize object by loading all the configuration files.
     *
     * @param array $files
     */
    protected function init(array $files)
    {
        $this->updated = true;

        // Combine all configuration files into one larger lookup table (only keys matter).
        $allFiles = $files['user'] + $files['plugins'] + $files['system'];

        // Then sort the files to have all parent nodes first.
        // This is to make sure that child nodes override parents content.
        uksort(
            $allFiles,
            function($a, $b) {
                $diff = substr_count($a, '/') - substr_count($b, '/');
                return $diff ? $diff : strcmp($a, $b);
            }
        );

        $systemBlueprints = new Blueprints(SYSTEM_DIR . 'blueprints');
        $pluginBlueprints = new Blueprints(USER_DIR);

        $items = array();
        foreach ($allFiles as $name => $dummy) {
            $lookup = array(
                'system' => SYSTEM_DIR . 'config/' . $name . YAML_EXT,
                'plugins' => USER_DIR . $name . '/' . basename($name) . YAML_EXT,
                'user' => USER_DIR . 'config/' . $name . YAML_EXT,
            );
            if (strpos($name, 'plugins/') === 0) {
                $blueprint = $pluginBlueprints->get("{$name}/blueprints");
            } else {
                $blueprint = $systemBlueprints->get($name);
            }

            $data = new Data(array(), $blueprint);
            foreach ($lookup as $key => $path) {
                if (is_file($path)) {
                    $data->merge(File\Yaml::instance($path)->content());
                }
            }
//            $data->validate();
//            $data->filter();

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
        // Find all plugins with default configuration options.
        $plugins = array();
        $iterator = new \DirectoryIterator(PLUGINS_DIR);

        /** @var \DirectoryIterator $plugin */
        foreach ($iterator as $plugin) {
            $name = $plugin->getBasename();
            $file = $plugin->getPathname() . DS . $name . YAML_EXT;

            if (!is_file($file)) {
                continue;
            }

            $modified = filemtime($file);
            $plugins["plugins/{$name}"] = $modified;
        }

        // Find all system and user configuration files.
        $options = array(
            'compare' => 'Filename',
            'pattern' => '|\.yaml$|',
            'filters' => array('key' => '|\.yaml$|'),
            'key' => 'SubPathname',
            'value' => 'MTime'
        );

        $system = Folder::all(SYSTEM_DIR . 'config', $options);
        $user = Folder::all(USER_DIR . 'config', $options);

        return array('system' => $system, 'plugins' => $plugins, 'user' => $user);
    }
}
