<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Data\Blueprints;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Flex\Flex;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class PagesFlex
{
    /** @var Grav */
    protected $grav;

    /** @var PageInterface[] */
    protected $instances;

    /** @var array */
    protected $children;

    /** @var string */
    protected $base = '';

    /** @var string[] */
    protected $routes = [];

    /** @var array */
    protected $sort;

    /** @var Blueprints */
    protected $blueprints;

    /** @var int */
    protected $last_modified;

    /** @var string[] */
    protected $ignore_files;

    /** @var string[] */
    protected $ignore_folders;

    /** @var bool */
    protected $ignore_hidden;

    /** @var bool */
    protected $initialized = false;

    /** @var Types */
    static protected $types;

    /**
     * Class initialization. Must be called before using this class.
     */
    public function init()
    {
        if ($this->initialized) {
            return;
        }

        $config = $this->grav['config'];
        $this->ignore_files = $config->get('system.pages.ignore_files');
        $this->ignore_folders = $config->get('system.pages.ignore_folders');
        $this->ignore_hidden = $config->get('system.pages.ignore_hidden');

        $this->instances = [];
        $this->children = [];
        $this->routes = [];

        $this->buildPages();
    }

    /**
     * Get or set last modification time.
     *
     * @param int $modified
     *
     * @return int|null
     */
    public function lastModified($modified = null)
    {
        if ($modified && $modified > $this->last_modified) {
            $this->last_modified = $modified;
        }

        return $this->last_modified;
    }

    /**
     * Returns a list of all pages.
     *
     * @return array|PageInterface[]
     */
    public function instances()
    {
        return $this->instances;
    }

    /**
     * Get root page.
     *
     * @return PageInterface
     */
    public function root()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        return $this->instances[rtrim($locator->findResource('page://'), DS)];
    }

    /**
     * Builds pages.
     *
     * @internal
     */
    protected function buildPages()
    {
        /** @var Flex $flex */
        $flex = $this->grav['flex_objects'] ?? null;
        $directory = $flex ? $flex->getDirectory('grav-pages') : null;

        // TODO: right now we are just emulating normal pages, it is inefficient and bad... but works!
        $collection = $directory->getCollection();
        $root = $collection->getRoot();
        $instances = [
            $root->path() => $root
        ];
        $children = [];

        foreach ($collection as $key => $page) {
            $path = $page->path();
            $parent = dirname($path);

            $instances[$path] = $page;
            $children[$parent][$path] = ['slug' => $page->slug()];
            if (!isset($children[$path])) {
                $children[$path] = [];
            }
        }

        $this->instances = $instances;
        $this->children = $children;
    }
}
