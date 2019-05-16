<?php

/**
 * @package    Grav\Common\Data
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Blueprints
{
    /** @var array|string */
    protected $search;
    /** @var array */
    protected $types;
    /** @var array */
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
            $blueprint = $this->loadFile($type);
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
            $this->types = [];

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
                if (!$file->isFile() || '.' . $file->getExtension() !== YAML_EXT) {
                    continue;
                }
                $name = $file->getBasename(YAML_EXT);
                $this->types[$name] = ucfirst(str_replace('_', ' ', $name));
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

        if (\is_array($this->search) || \is_object($this->search)) {
            // Page types.
            $blueprint->setOverrides($this->search);
            $blueprint->setContext('blueprints://pages');
        } else {
            $blueprint->setContext($this->search);
        }

        try {
            $blueprint->load()->init();
        } catch (\RuntimeException $e) {
            $log = Grav::instance()['log'];
            $log->error(sprintf('Blueprint %s cannot be loaded: %s', $name, $e->getMessage()));

            throw $e;
        }

        return $blueprint;
    }
}
