<?php
namespace Grav\Common\Data;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\GravTrait;

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
        if (!is_string($search)) {
            $this->search = $search;
        } else {
            $this->search = rtrim($search, '\\/') . '/';
        }
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

            $iterator   = new \DirectoryIterator($this->search);
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
