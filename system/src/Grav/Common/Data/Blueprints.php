<?php
namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
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
    use GravTrait;

    protected $search;
    protected $types;
    protected $instances = array();

    /**
     * @param  string|array  $search  Search path.
     */
    public function __construct($search)
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
            if (is_string($this->search)) {
                $filename = $this->search . $type . YAML_EXT;

                // Check if search is a stream and resolve the path.
                if (strpos($filename, '://')) {
                    $grav = static::getGrav();
                    /** @var UniformResourceLocator $locator */
                    $locator = $grav['locator'];
                    $filename = $locator($filename);
                }
            } else {
                $filename = isset($this->search[$type]) ? $this->search[$type] : '';
            }

            if ($filename && is_file($filename)) {
                $file = CompiledYamlFile::instance($filename);
                $blueprints = $file->content();
            } else {
                $blueprints = [];
            }

            $blueprint = new Blueprint($type, $blueprints, $this);

            if (isset($blueprints['@extends'])) {
                // Extend blueprint by other blueprints.
                $extends = (array) $blueprints['@extends'];

                if (is_string(key($extends))) {
                    $extends = [ $extends ];
                }

                foreach ($extends as $extendConfig) {
                    $extendType = !is_string($extendConfig) ? empty($extendConfig['type']) ? false : $extendConfig['type'] : $extendConfig;

                    if (!$extendType) {
                        continue;
                    }

                    $context = is_string($extendConfig) || empty($extendConfig['context']) ? $this : new self(self::getGrav()['locator']->findResource($extendConfig['context']));
                    $blueprint->extend($context->get($extendType));
                }
            }

            $this->instances[$type] = $blueprint;
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
                $grav = static::getGrav();
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
}
