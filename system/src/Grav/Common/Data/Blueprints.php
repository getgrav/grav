<?php
/**
 * @package    Grav.Common.Data
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

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

            $grav = Grav::instance();

            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            // Get stream / directory iterator.
            if ($locator->isStream($this->search)) {
                $iterator = $locator->getIterator($this->search);
            } else {
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

        if (is_array($this->search) || is_object($this->search)) {
            // Page types.
            $blueprint->setOverrides($this->search);
            $blueprint->setContext('blueprints://pages');
        } else {
            $blueprint->setContext($this->search);
        }

        return $blueprint->load()->init();
    }
}
