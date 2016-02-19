<?php
namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Blueprints class keeps track on blueprint instances.
 *
 * @author RocketTheme
 * @license MIT
 */
class Blueprints
{
    protected $search;
    protected $types;
    protected $instances = array();

    /**
     * @param  string|array  $search  Search path.
     */
    public function __construct($search = 'blueprints://')
    {
        $this->search = $search;
    }

    /**
     * Get blueprint.
     *
     * @param  string  $type  Blueprint type.
     * @return Blueprint
     * @throws \RuntimeException
     */
    public function get($type)
    {
        if (!isset($this->instances[$type])) {
            if (!is_string($this->search)) {
                $filename = isset($this->search[$type]) ? $this->search[$type] : null;
            } else {
                $filename = $this->search . $type . YAML_EXT;
            }

            // Check if search is a stream and resolve the path.
            if ($filename && strpos($filename, '://')) {
                /** @var UniformResourceLocator $locator */
                $locator = Grav::instance()['locator'];

                $files = $locator->findResources($filename);
            } else {
                $files = (array) $filename;
            }

            $blueprint = $this->loadFile($type, $files);

            $this->instances[$type] = $blueprint->init();
        }

        return $this->instances[$type];
    }

    /**
     * Get all available blueprint types.
     *
     * @return  array  List of type=>name
     */
    public function types()
    {
        if ($this->types === null) {
            $this->types = array();

            // Check if search is a stream.
            if (strpos($this->search, '://')) {
                // Stream: use UniformResourceIterator.
                $grav = Grav::instance();
                /** @var UniformResourceLocator $locator */
                $locator = $grav['locator'];
                $iterator = $locator->getIterator($this->search, null);
            } else {
                // Not a stream: use DirectoryIterator.
                $iterator = new \DirectoryIterator($this->search);
            }

            /** @var \DirectoryIterator $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || '.' . $file->getExtension() != YAML_EXT) {
                    continue;
                }
                $name = $file->getBasename(YAML_EXT);
                $this->types[$name] = ucfirst(strtr($name, '_', ' '));
            }
        }
        return $this->types;
    }


    /**
     * Load single configuration file and make a blueprint.
     *
     * @param  string  $name  Name of the position.
     * @param  array   $files  Files to be loaded.
     * @return Blueprint
     */
    protected function loadFile($name, $files)
    {
        $blueprint = new Blueprint($name, [], $this);

        $data = $this->loadBlueprints($files);

        // Merge all extends into a single blueprint.
        foreach ($data as $content) {
            $blueprint->embed('', $content, '/', true);
        }

        $blueprint->init('static');

        return $blueprint;
    }

    /**
     * Internal function that handles loading extended blueprints.
     *
     * @param array $files
     * @return array
     */
    protected function loadBlueprints(array $files)
    {
        $filename = array_shift($files);
        $file = CompiledYamlFile::instance($filename);
        $content = $file->content();
        $file->free();

        $extends = isset($content['@extends']) ? (array) $content['@extends']
            : (isset($content['extends@']) ? (array) $content['extends@'] : null);

        $data = isset($extends) ? $this->extendBlueprint($files, $extends) : [];
        $data[] = $content;

        return $data;
    }

    /**
     * Internal function to recursively load extended blueprints.
     *
     * @param array $parents
     * @param array $extends
     * @return array
     */
    protected function extendBlueprint(array $parents, array $extends)
    {
        if (is_string(key($extends))) {
            $extends = [$extends];
        }

        $data = [];
        foreach ($extends as $extendConfig) {
            // Accept array of type and context or a string.
            $extendType = !is_string($extendConfig)
                ? !isset($extendConfig['type']) ? null : $extendConfig['type'] : $extendConfig;

            if (!$extendType) {
                continue;
            }

            if ($extendType === '@parent' || $extendType === 'parent@') {
                $files = $parents;

            } else {
                if (strpos($extendType, '://')) {
                    $path = $extendType;
                } elseif (empty($extendConfig['context'])) {
                    $path = "blueprints://{$extendType}";
                } else {
                    $separator = $extendConfig['context'][strlen($extendConfig['context'])-1] === '/' ? '' : '/';
                    $path = $extendConfig['context'] . $separator . $extendType;
                }
                if (!preg_match('/\.yaml$/', $path)) {
                    $path .= '.yaml';
                }

                /** @var UniformResourceLocator $locator */
                $locator = Grav::instance()['locator'];

                $files = $locator->findResources($path);
            }

            if ($files) {
                $data = array_merge($data, $this->loadBlueprints($files));
            }
        }

        return $data;
    }
}
