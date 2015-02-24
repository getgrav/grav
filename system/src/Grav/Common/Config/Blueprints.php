<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;
use RocketTheme\Toolbox\Blueprints\Blueprints as BaseBlueprints;
use RocketTheme\Toolbox\File\PhpFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Blueprints class contains configuration rules.
 *
 * @author RocketTheme
 * @license MIT
 */
class Blueprints extends BaseBlueprints
{
    protected $grav;
    protected $files = [];
    protected $blueprints;

    public function __construct(array $serialized = null, Grav $grav = null)
    {
        parent::__construct($serialized);
        $this->grav = $grav ?: Grav::instance();
    }

    public function init()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $blueprints = $locator->findResources('blueprints://config');
        $plugins = $locator->findResources('plugins://');

        $blueprintFiles = $this->getBlueprintFiles($blueprints, $plugins);

        $this->loadCompiledBlueprints($plugins + $blueprints, $blueprintFiles);
    }

    protected function loadCompiledBlueprints($blueprints, $blueprintFiles)
    {
        $checksum = md5(serialize($blueprints));
        $filename = CACHE_DIR . 'compiled/blueprints/' . $checksum .'.php';
        $checksum .= ':'.md5(serialize($blueprintFiles));
        $class = get_class($this);
        $file = PhpFile::instance($filename);

        if ($file->exists()) {
            $cache = $file->exists() ? $file->content() : null;
        } else {
            $cache = null;
        }


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

    /**
     * Load global blueprints.
     *
     * @param string $key
     * @param array $files
     */
    public function loadBlueprints($key, array $files = null)
    {
        if (is_null($files)) {
            $files = $this->files[$key];
        }
        foreach ($files as $name => $item) {
            $file = CompiledYamlFile::instance($item['file']);
            $this->blueprints->embed($name, $file->content(), '/');
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
}
