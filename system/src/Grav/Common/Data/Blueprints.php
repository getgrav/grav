<?php
namespace Grav\Common\Data;

use Grav\Common\Grav;
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
    protected $instances = [];

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
            $this->instances[$type] = $this->loadFile($type);
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
     * Load blueprint file.
     *
     * @param  string  $name  Name of the blueprint.
     * @return Blueprint
     */
    protected function loadFile($name)
    {
        $blueprint = new Blueprint($name);

        if (is_array($this->search)) {
            $blueprint->setOverrides($this->search);
        } else {
            $blueprint->setContext($this->search);
        }

        return $blueprint->load();
    }
}
