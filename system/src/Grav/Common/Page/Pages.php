<?php
namespace Grav\Common\Page;

use \Grav\Common\Filesystem\Folder;
use \Grav\Common\Grav;
use \Grav\Common\Config;
use \Grav\Common\Data;
use \Grav\Common\Registry;
use \Grav\Common\Utils;
use \Grav\Common\Cache;
use \Grav\Common\Taxonomy;

/**
 * GravPages is the class that is the entry point into the hierarchy of pages
 */
class Pages
{
    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array|Page[]
     */
    protected $instances;

    /**
     * @var array|string[]
     */
    protected $children;

    /**
     * @var array|string[]
     */
    protected $routes;

    /**
     * @var array
     */
    protected $sort;

    /**
     * @var Data\Blueprints
     */
    protected $blueprints;

    /**
     * @var int
     */
    protected $last_modified;

    /**
     * Class initialization. Must be called before using this class.
     */
    public function init()
    {
        $this->grav = Registry::get('Grav');
        $this->config = Registry::get('Config');

        $this->buildPages();
    }

    /**
     * Get or set last modification time.
     *
     * @param int $modified
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
     * @return Page
     */
    public function instances()
    {
        return $this->instances;
    }

    /**
     * Returns a list of all routes.
     *
     * @return array
     */
    public function routes()
    {
        return $this->routes;
    }

    /**
     * Adds a page and assigns a route to it.
     *
     * @param Page   $page   Page to be added.
     * @param string $route  Optional route (uses route from the object if not set).
     */
    public function addPage(Page $page, $route = null)
    {
        if (!isset($this->instances[$page->path()])) {
            $this->instances[$page->path()] = $page;
        }
        $route = $page->route($route);
        if ($page->parent()) {
            $this->children[$page->parent()->path()][$page->path()] = array('slug' => $page->slug());
        }
        $this->routes[$route] = $page->path();
    }

    /**
     * Sort sub-pages in a page.
     *
     * @param Page $page
     * @param string $order_by
     * @param string $order_dir
     *
     * @return array
     */
    public function sort(Page $page, $order_by = null, $order_dir = null)
    {
        if ($order_by === null) {
            $order_by = $page->orderBy();
        }
        if ($order_dir === null) {
            $order_dir = $page->orderDir();
        }

        $path = $page->path();
        $children = isset($this->children[$path]) ? $this->children[$path] : array();

        if (!$children) {
            return $children;
        }

        if (!isset($this->sort[$path][$order_by])) {
            $this->buildSort($path, $children, $order_by, $page->orderManual());
        }

        $sort = $this->sort[$path][$order_by];

        if ($order_dir != 'asc') {
            $sort = array_reverse($sort);
        }

        return $sort;
    }

    /**
     * @param Collection $collection
     * @param $orderBy
     * @param string $orderDir
     * @param null $orderManual
     * @return array
     * @internal
     */
    public function sortCollection(Collection $collection, $orderBy, $orderDir = 'asc', $orderManual = null)
    {
        $items = $collection->toArray();

        $lookup = md5(serialize($items));
        if (!isset($this->sort[$lookup][$orderBy])) {
            $this->buildSort($lookup, $items, $orderBy, $orderManual);
        }

        $sort = $this->sort[$lookup][$orderBy];

        if ($orderDir != 'asc') {
            $sort = array_reverse($sort);
        }

        return $sort;

    }

    /**
     * Get a page instance.
     *
     * @param  string  $path
     * @return Page
     */
    public function get($path)
    {
        if (!is_null($path) && !is_string($path)) throw new \Exception();
        return isset($this->instances[(string) $path]) ? $this->instances[(string) $path] : null;
    }

    /**
     * Get children of the path.
     *
     * @param string $path
     * @return Collection
     */
    public function children($path)
    {
        $children = isset($this->children[(string) $path]) ? $this->children[(string) $path] : array();
        return new Collection($children, array(), $this);
    }

    /**
     * Dispatch URI to a page.
     *
     * @param $url
     * @param bool $all
     * @return Page|null
     */
    public function dispatch($url, $all = false)
    {
        // Fetch page if there's a defined route to it.
        $page = isset($this->routes[$url]) ? $this->get($this->routes[$url]) : null;

        // If the page cannot be reached, look into site wide routes.
        if (!$all && (!$page || !$page->routable())) {
            $route = $this->config->get("site.routes.{$url}");
            if ($route) {
                $page = $this->dispatch($route, $all);
            }
        }

        return $page;
    }

    /**
     * Get root page.
     *
     * @return Page
     */
    public function root()
    {
        return $this->instances[rtrim(PAGES_DIR, DS)];
    }

    /**
     * Get a blueprint for a page type.
     *
     * @param  string  $type
     * @return Data\Blueprint
     */
    public function blueprints($type)
    {
        if (!isset($this->blueprints)) {
            $this->blueprints = new Data\Blueprints(THEMES_DIR . $this->config->get('system.pages.theme') . '/blueprints/');
        }

        try {
            $blueprint = $this->blueprints->get($type);
        } catch (\RuntimeException $e) {
            $blueprint = $this->blueprints->get('default');
        }

        if (!$blueprint->initialized) {
            /** @var Grav $grav */
            $grav = Registry::get('Grav');
            $grav->fireEvent('onCreateBlueprint', $blueprint);
            $blueprint->initialized = true;
        }

        return $blueprint;
    }

    /**
     * Get list of route/title of all pages.
     *
     * @param Page $current
     * @param int $level
     * @return array
     * @throws \RuntimeException
     */
    public function getList(Page $current = null, $level = 0)
    {
        if (!$current) {
            if ($level) {
                throw new \RuntimeException('Internal error');
            }

            $current = $this->root();
        }

        $list = array();
        if ($current->routable()) {
            $list[$current->route()] = str_repeat('&nbsp; ', ($level-1)*2) . $current->title();
        }

        foreach ($current as $next) {
            $list = array_merge($list, $this->getList($next, $level + 1));
        }

        return $list;
    }

    /**
     * Get available page types.
     *
     * @return array
     */
    static public function types()
    {
        /** @var Config $config */
        $config = Registry::get('Config');
        $blueprints = new Data\Blueprints(THEMES_DIR . $config->get('system.pages.theme') . '/blueprints/');

        return $blueprints->types();
    }

    /**
     * Get available parents.
     *
     * @return array
     */
    static public function parents()
    {
        /** @var Pages $pages */
        $pages = Registry::get('Pages');
        return $pages->getList();
    }

    /**
     * Builds pages.
     *
     * @internal
     */
    protected function buildPages()
    {
        $this->sort = array();
        if ($this->config->get('system.cache.enabled')) {
            /** @var Cache $cache */
            $cache = Registry::get('Cache');
            /** @var Taxonomy $taxonomy */
            $taxonomy = Registry::get('Taxonomy');
            $last_modified = $this->config->get('system.cache.check.pages', true)
                ? Folder::lastModified(PAGES_DIR) : 0;
            $page_cache_id = md5(USER_DIR.$last_modified);

            list($this->instances, $this->routes, $this->children, $taxonomy_map, $this->sort) = $cache->fetch($page_cache_id);
            if (!$this->instances) {
                $this->recurse();
                $this->buildRoutes();

                // save pages, routes, taxonomy, and sort to cache
                $cache->save(
                    $page_cache_id,
                    array($this->instances, $this->routes, $this->children, $taxonomy->taxonomy(), $this->sort)
                );
            } else {
                // If pages was found in cache, set the taxonomy
                $taxonomy->taxonomy($taxonomy_map);
            }
        } else {
            $this->recurse();
            $this->buildRoutes();
        }
    }

    /**
     * Recursive function to load & build page relationships.
     *
     * @param string $directory
     * @param null $parent
     * @return Page
     * @throws \RuntimeException
     * @internal
     */
    protected function recurse($directory = PAGES_DIR, &$parent = null)
    {
        $directory  = rtrim($directory, DS);
        $iterator   = new \DirectoryIterator($directory);
        $page       = new Page;

        $page->path($directory);
        $page->parent($parent);
        $page->orderDir($this->config->get('system.pages.order.dir'));
        $page->orderBy($this->config->get('system.pages.order.by'));

        // Add into instances
        if (!isset($this->instances[$page->path()])) {
            $this->instances[$page->path()] = $page;
            if ($parent && $page->path()) {
                $this->children[$parent->path()][$page->path()] = array('slug' => $page->slug());
            }
        } else {
            throw new \RuntimeException('Fatal error when creating page instances.');
        }

        /** @var \DirectoryIterator $file */
        foreach ($iterator as $file) {
            $name = $file->getFilename();

            if ($file->isFile() && Utils::endsWith($name, CONTENT_EXT)) {

                $page->init($file);

                if ($this->config->get('system.pages.events.page')) {
                    $this->grav->fireEvent('onAfterPageProcessed', $page);
                }

            } elseif ($file->isDir() && !$file->isDot()) {

                if (!$page->path()) {
                    $page->path($file->getPath());
                }

                $path = $directory.DS.$name;
                $child = $this->recurse($path, $page);

                if (Utils::startsWith($name, '_')) {
                    $child->routable(false);
                }

                $this->children[$page->path()][$child->path()] = array('slug' => $child->slug());

                // set the modified time if not already set
                if (!$page->date()) {
                    $page->date($file->getMTime());
                }

                // set the last modified time on pages
                $this->lastModified($file->getMTime());

                if ($this->config->get('system.pages.events.page')) {
                    $this->grav->fireEvent('onAfterFolderProcessed', $page);
                }
            }
        }

        // Sort based on Defaults or Page Overridden sort order
        $this->children[$page->path()] = $this->sort($page);

        return $page;
    }

    /**
     * @internal
     */
    protected function buildRoutes()
    {
        /** @var $taxonomy Taxonomy */
        $taxonomy = Registry::get('Taxonomy');

        // Build routes and taxonomy map.
        /** @var $page Page */
        foreach ($this->instances as $page) {

            $parent = $page->parent();

            if ($parent) {
                $route = rtrim($parent->route(), '/') . '/' . $page->slug();
                $this->routes[$route] = $page->path();
                $page->route($route);
            }

            if (!empty($route)) {
                $taxonomy->addTaxonomy($page);
            } else {
                $page->routable(false);
            }
        }

        // Alias and set default route to home page.
        $home = trim($this->config->get('system.home.alias'), '/');
        if ($home && isset($this->routes['/' . $home])) {
            $this->routes['/'] = $this->routes['/' . $home];
            $this->get($this->routes['/' . $home])->route('/');
        }
    }

    /**
     * @param string $path
     * @param array $pages
     * @param string $order_by
     * @param array $manual
     * @throws \RuntimeException
     * @internal
     */
    protected function buildSort($path, array $pages, $order_by = 'default', $manual = null)
    {
        $list = array();

        foreach ($pages as $key => $info) {

            $child = isset($this->instances[$key]) ? $this->instances[$key] : null;
            if (!$child) {
                throw new \RuntimeException("Page does not exist: {$key}");
            }

            switch ($order_by) {
                case 'title':
                    $list[$key] = $child->title();
                    break;
                case 'date':
                    $list[$key] = $child->date();
                    break;
                case 'modified':
                    $list[$key] = $child->modified();
                    break;
                case 'slug':
                    $list[$key] = $info['slug'];
                    break;
                case 'basename':
                    $list[$key] = basename($key);
                    break;
                case 'manual':
                case 'default':
                default:
                    $list[$key] = $key;
            }
        }

        // Sort by the new list.
        asort($list);

        // Move manually ordered items into the beginning of the list. Order of the unlisted items does not change.
        if (is_array($manual) && !empty($manual)) {
            $new_list = array();
            $i = count($manual);

            foreach ($list as $key => $dummy) {
                $info = $pages[$key];
                $order = array_search($info['slug'], $manual);
                if ($order === false) {
                    $order = $i++;
                }
                $new_list[$key] = (int) $order;
            }

            $list = $new_list;

            // Apply manual ordering to the list.
            asort($list);
        }

        foreach ($list as $key => $sort) {
            $info = $pages[$key];
            // TODO: order by manual needs a hash from the passed variables if we make this more general.
            $this->sort[$path][$order_by][$key] = $info;
        }
    }
}
