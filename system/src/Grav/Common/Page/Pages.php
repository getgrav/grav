<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Blueprints;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Taxonomy;
use Grav\Common\Utils;
use Grav\Plugin\Admin;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Whoops\Exception\ErrorException;
use Collator as Collator;

class Pages
{
    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var array|Page[]
     */
    protected $instances;

    /**
     * @var array|string[]
     */
    protected $children;

    /**
     * @var string
     */
    protected $base = '';

    /**
     * @var array|string[]
     */
    protected $baseUrl = [];

    /**
     * @var array|string[]
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $sort;

    /**
     * @var Blueprints
     */
    protected $blueprints;

    /**
     * @var int
     */
    protected $last_modified;

    /**
     * @var array|string[]
     */
    protected $ignore_files;

    /**
     * @var array|string[]
     */
    protected $ignore_folders;

    /**
     * @var bool
     */
    protected $ignore_hidden;

    /**
     * @var Types
     */
    static protected $types;

    /**
     * @var string
     */
    static protected $home_route;

    protected $pages_cache_id;

    /**
     * Constructor
     *
     * @param Grav $c
     */
    public function __construct(Grav $c)
    {
        $this->grav = $c;
    }

    /**
     * Get or set base path for the pages.
     *
     * @param  string $path
     *
     * @return string
     */
    public function base($path = null)
    {
        if ($path !== null) {
            $path = trim($path, '/');
            $this->base = $path ? '/' . $path : null;
            $this->baseUrl = [];
        }

        return $this->base;
    }

    /**
     *
     * Get base URL for Grav pages.
     *
     * @param  string $lang     Optional language code for multilingual links.
     * @param  bool   $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
     *
     * @return string
     */
    public function baseUrl($lang = null, $absolute = null)
    {
        $lang = (string) $lang;
        $type = $absolute === null ? 'base_url' : ($absolute ? 'base_url_absolute' : 'base_url_relative');
        $key = "{$lang} {$type}";

        if (!isset($this->baseUrl[$key])) {
            /** @var Config $config */
            $config = $this->grav['config'];

            /** @var Language $language */
            $language = $this->grav['language'];

            if (!$lang) {
                $lang = $language->getActive();
            }

            $path_append = rtrim($this->grav['pages']->base(), '/');
            if ($language->getDefault() != $lang || $config->get('system.languages.include_default_lang') === true) {
                $path_append .= $lang ? '/' . $lang : '';
            }

            $this->baseUrl[$key] = $this->grav[$type] . $path_append;
        }

        return $this->baseUrl[$key];
    }

    /**
     *
     * Get home URL for Grav site.
     *
     * @param  string $lang     Optional language code for multilingual links.
     * @param  bool   $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
     *
     * @return string
     */
    public function homeUrl($lang = null, $absolute = null)
    {
        return $this->baseUrl($lang, $absolute) ?: '/';
    }

    /**
     *
     * Get home URL for Grav site.
     *
     * @param  string $route    Optional route to the page.
     * @param  string $lang     Optional language code for multilingual links.
     * @param  bool   $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
     *
     * @return string
     */
    public function url($route = '/', $lang = null, $absolute = null)
    {
        if ($route === '/') {
            return $this->homeUrl($lang, $absolute);
        }

        return $this->baseUrl($lang, $absolute) . $route;
    }

    /**
     * Class initialization. Must be called before using this class.
     */
    public function init()
    {
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
     * @return array|Page[]
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
     * @param Page   $page  Page to be added.
     * @param string $route Optional route (uses route from the object if not set).
     */
    public function addPage(Page $page, $route = null)
    {
        if (!isset($this->instances[$page->path()])) {
            $this->instances[$page->path()] = $page;
        }
        $route = $page->route($route);
        if ($page->parent()) {
            $this->children[$page->parent()->path()][$page->path()] = ['slug' => $page->slug()];
        }
        $this->routes[$route] = $page->path();
    }

    /**
     * Sort sub-pages in a page.
     *
     * @param Page   $page
     * @param string $order_by
     * @param string $order_dir
     *
     * @return array
     */
    public function sort(Page $page, $order_by = null, $order_dir = null, $sort_flags = null)
    {
        if ($order_by === null) {
            $order_by = $page->orderBy();
        }
        if ($order_dir === null) {
            $order_dir = $page->orderDir();
        }

        $path = $page->path();
        $children = isset($this->children[$path]) ? $this->children[$path] : [];

        if (!$children) {
            return $children;
        }

        if (!isset($this->sort[$path][$order_by])) {
            $this->buildSort($path, $children, $order_by, $page->orderManual(), $sort_flags);
        }

        $sort = $this->sort[$path][$order_by];

        if ($order_dir != 'asc') {
            $sort = array_reverse($sort);
        }

        return $sort;
    }

    /**
     * @param Collection $collection
     * @param            $orderBy
     * @param string     $orderDir
     * @param null       $orderManual
     *
     * @return array
     * @internal
     */
    public function sortCollection(Collection $collection, $orderBy, $orderDir = 'asc', $orderManual = null, $sort_flags = null)
    {
        $items = $collection->toArray();
        if (!$items) {
            return [];
        }

        $lookup = md5(json_encode($items) . json_encode($orderManual) . $orderBy . $orderDir);
        if (!isset($this->sort[$lookup][$orderBy])) {
            $this->buildSort($lookup, $items, $orderBy, $orderManual, $sort_flags);
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
     * @param  string $path The filesystem full path of the page
     *
     * @return Page
     * @throws \Exception
     */
    public function get($path)
    {
        if (!is_null($path) && !is_string($path)) {
            throw new \Exception();
        }

        return isset($this->instances[(string)$path]) ? $this->instances[(string)$path] : null;
    }

    /**
     * Get children of the path.
     *
     * @param string $path
     *
     * @return Collection
     */
    public function children($path)
    {
        $children = isset($this->children[(string)$path]) ? $this->children[(string)$path] : [];

        return new Collection($children, [], $this);
    }

    /**
     * Get a page ancestor.
     *
     * @param  string $route The relative URL of the page
     * @param  string $path The relative path of the ancestor folder
     *
     * @return Page|null
     */
    public function ancestor($route, $path = null)
    {
        if (!is_null($path)) {

            $page = $this->dispatch($route, true);

            if ($page->path() == $path) {
                return $page;
            } elseif (!$page->parent()->root()) {
                return $this->ancestor($page->parent()->route(), $path);
            }
        }

        return null;
    }

    /**
     * Get a page ancestor trait.
     *
     * @param  string $route The relative route of the page
     * @param  string $field The field name of the ancestor to query for
     *
     * @return Page|null
     */
    public function inherited($route, $field = null)
    {
        if (!is_null($field)) {

            $page = $this->dispatch($route, true);

            $ancestorField = $page->parent()->value('header.' . $field);

            if ($ancestorField != null) {
                return $page->parent();
            } elseif (!$page->parent()->root()) {
                return $this->inherited($page->parent()->route(), $field);
            }
        }

        return null;
    }

    /**
     * alias method to return find a page.
     *
     * @param string $route The relative URL of the page
     * @param bool   $all
     *
     * @return Page|null
     */
    public function find($route, $all = false)
    {
        return $this->dispatch($route, $all, false);
    }

    /**
     * Dispatch URI to a page.
     *
     * @param string $route The relative URL of the page
     * @param bool $all
     *
     * @param bool $redirect
     * @return Page|null
     * @throws \Exception
     */
    public function dispatch($route, $all = false, $redirect = true)
    {
        // Fetch page if there's a defined route to it.
        $page = isset($this->routes[$route]) ? $this->get($this->routes[$route]) : null;
        // Try without trailing slash
        if (!$page && Utils::endsWith($route, '/')) {
            $page = isset($this->routes[rtrim($route, '/')]) ? $this->get($this->routes[rtrim($route, '/')]) : null;
        }

        // Are we in the admin? this is important!
        $not_admin = !isset($this->grav['admin']);

        // If the page cannot be reached, look into site wide redirects, routes + wildcards
        if (!$all && $not_admin) {

            // If the page is a simple redirect, just do it.
            if ($redirect && $page && $page->redirect()) {
                $this->grav->redirectLangSafe($page->redirect());
            }

            // fall back and check site based redirects
            if (!$page || ($page && !$page->routable())) {
                /** @var Config $config */
                $config = $this->grav['config'];

                // See if route matches one in the site configuration
                $site_route = $config->get("site.routes.{$route}");
                if ($site_route) {
                    $page = $this->dispatch($site_route, $all);
                } else {
                    // Try Regex style redirects
                    $uri = $this->grav['uri'];
                    $source_url = $route;
                    $extension = $uri->extension();
                    if (isset($extension) && !Utils::endsWith($uri->url(), $extension)) {
                        $source_url.= '.' . $extension;
                    }

                    $site_redirects = $config->get("site.redirects");
                    if (is_array($site_redirects)) {
                        foreach ((array)$site_redirects as $pattern => $replace) {
                            $pattern = '#^' . str_replace('/', '\/', ltrim($pattern, '^')) . '#';
                            try {
                                $found = preg_replace($pattern, $replace, $source_url);
                                if ($found != $source_url) {
                                    $this->grav->redirectLangSafe($found);
                                }
                            } catch (ErrorException $e) {
                                $this->grav['log']->error('site.redirects: ' . $pattern . '-> ' . $e->getMessage());
                            }
                        }
                    }

                    // Try Regex style routes
                    $site_routes = $config->get("site.routes");
                    if (is_array($site_routes)) {
                        foreach ((array)$site_routes as $pattern => $replace) {
                            $pattern = '#^' . str_replace('/', '\/', ltrim($pattern, '^')) . '#';
                            try {
                                $found = preg_replace($pattern, $replace, $source_url);
                                if ($found != $source_url) {
                                    $page = $this->dispatch($found, $all);
                                }
                            } catch (ErrorException $e) {
                                $this->grav['log']->error('site.routes: ' . $pattern . '-> ' . $e->getMessage());
                            }
                        }
                    }
                }
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
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        return $this->instances[rtrim($locator->findResource('page://'), DS)];
    }

    /**
     * Get a blueprint for a page type.
     *
     * @param  string $type
     *
     * @return Blueprint
     */
    public function blueprints($type)
    {
        if (!isset($this->blueprints)) {
            $this->blueprints = new Blueprints(self::getTypes());
        }

        try {
            $blueprint = $this->blueprints->get($type);
        } catch (\RuntimeException $e) {
            $blueprint = $this->blueprints->get('default');
        }

        if (empty($blueprint->initialized)) {
            $this->grav->fireEvent('onBlueprintCreated', new Event(['blueprint' => $blueprint, 'type' => $type]));
            $blueprint->initialized = true;
        }

        return $blueprint;
    }

    /**
     * Get all pages
     *
     * @param \Grav\Common\Page\Page $current
     *
     * @return \Grav\Common\Page\Collection
     */
    public function all(Page $current = null)
    {
        $all = new Collection();

        /** @var Page $current */
        $current = $current ?: $this->root();

        if (!$current->root()) {
            $all[$current->path()] = ['slug' => $current->slug()];
        }

        foreach ($current->children() as $next) {
            $all->append($this->all($next));
        }

        return $all;
    }

    /**
     * Get available parents raw routes.
     *
     * @return array
     */
    public static function parentsRawRoutes()
    {
        $rawRoutes = true;

        return self::getParents($rawRoutes);
    }

    /**
     * Get available parents routes
     *
     * @param bool $rawRoutes get the raw route or the normal route
     *
     * @return array
     */
    private static function getParents($rawRoutes)
    {
        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        $parents = $pages->getList(null, 0, $rawRoutes);

        if (isset($grav['admin'])) {
            // Remove current route from parents

            /** @var Admin $admin */
            $admin = $grav['admin'];

            $page = $admin->getPage($admin->route);
            $page_route = $page->route();
            if (isset($parents[$page_route])) {
                unset($parents[$page_route]);
            }

        }

        return $parents;
    }

    /**
     * Get list of route/title of all pages.
     *
     * @param Page $current
     * @param int $level
     * @param bool $rawRoutes
     *
     * @param bool $showAll
     * @param bool $showFullpath
     * @param bool $showSlug
     * @param bool $showModular
     * @param bool $limitLevels
     * @return array
     */
    public function getList(Page $current = null, $level = 0, $rawRoutes = false, $showAll = true, $showFullpath = false, $showSlug = false, $showModular = false, $limitLevels = false)
    {
        if (!$current) {
            if ($level) {
                throw new \RuntimeException('Internal error');
            }

            $current = $this->root();
        }

        $list = [];

        if (!$current->root()) {
            if ($rawRoutes) {
                $route = $current->rawRoute();
            } else {
                $route = $current->route();
            }

            if ($showFullpath) {
                $option = $current->route();
            } else {
                $extra  = $showSlug ? '(' . $current->slug() . ') ' : '';
                $option = str_repeat('&mdash;-', $level). '&rtrif; ' . $extra . $current->title();


            }

            $list[$route] = $option;


        }

        if ($limitLevels == false || ($level+1 < $limitLevels)) {
            foreach ($current->children() as $next) {
                if ($showAll || $next->routable() || ($next->modular() && $showModular)) {
                    $list = array_merge($list, $this->getList($next, $level + 1, $rawRoutes, $showAll, $showFullpath, $showSlug, $showModular, $limitLevels));
                }
            }
        }

        return $list;
    }

    /**
     * Get available page types.
     *
     * @return Types
     */
    public static function getTypes()
    {
        if (!self::$types) {

            $grav = Grav::instance();

            $scanBlueprintsAndTemplates = function () use ($grav) {
                // Scan blueprints
                $event = new Event();
                $event->types = self::$types;
                $grav->fireEvent('onGetPageBlueprints', $event);

                self::$types->scanBlueprints('theme://blueprints/');

                // Scan templates
                $event = new Event();
                $event->types = self::$types;
                $grav->fireEvent('onGetPageTemplates', $event);

                self::$types->scanTemplates('theme://templates/');
            };

            if ($grav['config']->get('system.cache.enabled')) {
                /** @var Cache $cache */
                $cache = $grav['cache'];

                // Use cached types if possible.
                $types_cache_id = md5('types');
                self::$types = $cache->fetch($types_cache_id);

                if (!self::$types) {
                    self::$types = new Types();
                    $scanBlueprintsAndTemplates();
                    $cache->save($types_cache_id, self::$types);
                }

            } else {
                self::$types = new Types();
                $scanBlueprintsAndTemplates();
            }

        }

        return self::$types;
    }

    /**
     * Get available page types.
     *
     * @return array
     */
    public static function types()
    {
        $types = self::getTypes();

        return $types->pageSelect();
    }

    /**
     * Get available page types.
     *
     * @return array
     */
    public static function modularTypes()
    {
        $types = self::getTypes();

        return $types->modularSelect();
    }

    /**
     * Get template types based on page type (standard or modular)
     *
     * @return array
     */
    public static function pageTypes()
    {
        if (isset(Grav::instance()['admin'])) {
            /** @var Admin $admin */
            $admin = Grav::instance()['admin'];

            /** @var Page $page */
            $page = $admin->getPage($admin->route);

            if ($page && $page->modular()) {
                return static::modularTypes();
            }

            return static::types();
        }

        return [];
    }

    /**
     * Get access levels of the site pages
     *
     * @return array
     */
    public function accessLevels()
    {
        $accessLevels = [];
        foreach ($this->all() as $page) {
            if (isset($page->header()->access)) {
                if (is_array($page->header()->access)) {
                    foreach ($page->header()->access as $index => $accessLevel) {
                        if (is_array($accessLevel)) {
                            foreach ($accessLevel as $innerIndex => $innerAccessLevel) {
                                array_push($accessLevels, $innerIndex);
                            }
                        } else {
                            array_push($accessLevels, $index);
                        }
                    }
                } else {

                    array_push($accessLevels, $page->header()->access);
                }
            }
        }

        return array_unique($accessLevels);
    }

    /**
     * Get available parents routes
     *
     * @return array
     */
    public static function parents()
    {
        $rawRoutes = false;

        return self::getParents($rawRoutes);
    }



    /**
     * Gets the home route
     *
     * @return string
     */
    public static function getHomeRoute()
    {
        if (empty(self::$home_route)) {
            $grav = Grav::instance();

            /** @var Config $config */
            $config = $grav['config'];

            /** @var Language $language */
            $language = $grav['language'];

            $home = $config->get('system.home.alias');

            if ($language->enabled()) {
                $home_aliases = $config->get('system.home.aliases');
                if ($home_aliases) {
                    $active = $language->getActive();
                    $default = $language->getDefault();

                    try {
                        if ($active) {
                            $home = $home_aliases[$active];
                        } else {
                            $home = $home_aliases[$default];
                        }
                    } catch (ErrorException $e) {
                        $home = $home_aliases[$default];
                    }

                }
            }

            self::$home_route = trim($home, '/');
        }

        return self::$home_route;
    }

    /**
     * Needed for testing where we change the home route via config
     */
    public static function resetHomeRoute()
    {
        self::$home_route = null;
        return self::getHomeRoute();
    }

    /**
     * Builds pages.
     *
     * @internal
     */
    protected function buildPages()
    {
        $this->sort = [];

        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var Language $language */
        $language = $this->grav['language'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $pages_dir = $locator->findResource('page://');

        if ($config->get('system.cache.enabled')) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];

            // how should we check for last modified? Default is by file
            switch (strtolower($config->get('system.cache.check.method', 'file'))) {
                case 'none':
                case 'off':
                    $hash = 0;
                    break;
                case 'folder':
                    $hash = Folder::lastModifiedFolder($pages_dir);
                    break;
                case 'hash':
                    $hash = Folder::hashAllFiles($pages_dir);
                    break;
                default:
                    $hash = Folder::lastModifiedFile($pages_dir);
            }

            $this->pages_cache_id = md5($pages_dir . $hash . $language->getActive() . $config->checksum());

            list($this->instances, $this->routes, $this->children, $taxonomy_map, $this->sort) = $cache->fetch($this->pages_cache_id);
            if (!$this->instances) {
                $this->grav['debugger']->addMessage('Page cache missed, rebuilding pages..');

                // recurse pages and cache result
                $this->resetPages($pages_dir, $this->pages_cache_id);

            } else {
                // If pages was found in cache, set the taxonomy
                $this->grav['debugger']->addMessage('Page cache hit.');
                $taxonomy->taxonomy($taxonomy_map);
            }
        } else {
            $this->recurse($pages_dir);
            $this->buildRoutes();
        }
    }

    /**
     * Accessible method to manually reset the pages cache
     *
     * @param $pages_dir
     */
    public function resetPages($pages_dir)
    {
        $this->recurse($pages_dir);
        $this->buildRoutes();

        // cache if needed
        if ($this->grav['config']->get('system.cache.enabled')) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];

            // save pages, routes, taxonomy, and sort to cache
            $cache->save($this->pages_cache_id, [$this->instances, $this->routes, $this->children, $taxonomy->taxonomy(), $this->sort]);
        }
    }

    /**
     * Recursive function to load & build page relationships.
     *
     * @param string    $directory
     * @param Page|null $parent
     *
     * @return Page
     * @throws \RuntimeException
     * @internal
     */
    protected function recurse($directory, Page &$parent = null)
    {
        $directory = rtrim($directory, DS);
        $page = new Page;

        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var Language $language */
        $language = $this->grav['language'];

        // stuff to do at root page
        if ($parent === null) {

            // Fire event for memory and time consuming plugins...
            if ($config->get('system.pages.events.page')) {
                $this->grav->fireEvent('onBuildPagesInitialized');
            }
        }

        $page->path($directory);
        if ($parent) {
            $page->parent($parent);
        }

        $page->orderDir($config->get('system.pages.order.dir'));
        $page->orderBy($config->get('system.pages.order.by'));

        // Add into instances
        if (!isset($this->instances[$page->path()])) {
            $this->instances[$page->path()] = $page;
            if ($parent && $page->path()) {
                $this->children[$parent->path()][$page->path()] = ['slug' => $page->slug()];
            }
        } else {
            throw new \RuntimeException('Fatal error when creating page instances.');
        }

        $content_exists = false;
        $pages_found = new \GlobIterator($directory . '/*' . CONTENT_EXT);
        $page_found = null;

        $page_extension = '';

        if ($pages_found && count($pages_found) > 0) {

            $page_extensions = $language->getFallbackPageExtensions();

            foreach ($page_extensions as $extension) {
                foreach ($pages_found as $found) {
                    if ($found->isDir()) {
                        continue;
                    }
                    $regex = '/^[^\.]*' . preg_quote($extension) . '$/';
                    if (preg_match($regex, $found->getFilename())) {
                        $page_found = $found;
                        $page_extension = $extension;
                        break 2;
                    }
                }
            }
        }

        if ($parent && !empty($page_found)) {
            $page->init($page_found, $page_extension);

            $content_exists = true;

            if ($config->get('system.pages.events.page')) {
                $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
            }
        }

        // set current modified of page
        $last_modified = $page->modified();

        $iterator = new \FilesystemIterator($directory);

        /** @var \DirectoryIterator $file */
        foreach ($iterator as $file) {
            $name = $file->getFilename();

            // Ignore all hidden files if set.
            if ($this->ignore_hidden) {
                if ($name && $name[0] == '.') {
                    continue;
                }
            }

            if ($file->isFile()) {
                // Update the last modified if it's newer than already found
                if (!in_array($file->getBasename(), $this->ignore_files) && ($modified = $file->getMTime()) > $last_modified) {
                    $last_modified = $modified;
                }
            } elseif ($file->isDir() && !in_array($file->getFilename(), $this->ignore_folders)) {

                // if folder contains separator, continue
                if (Utils::contains($file->getFilename(), $config->get('system.param_sep', ':'))) {
                    continue;
                }

                if (!$page->path()) {
                    $page->path($file->getPath());
                }

                $path = $directory . DS . $name;
                $child = $this->recurse($path, $page);

                if (Utils::startsWith($name, '_')) {
                    $child->routable(false);
                }

                $this->children[$page->path()][$child->path()] = ['slug' => $child->slug()];

                if ($config->get('system.pages.events.page')) {
                    $this->grav->fireEvent('onFolderProcessed', new Event(['page' => $page]));
                }
            }
        }

        // Set routability to false if no page found
        if (!$content_exists) {
            $page->routable(false);
        }

        // Override the modified time if modular
        if ($page->template() == 'modular') {
            foreach ($page->collection() as $child) {
                $modified = $child->modified();

                if ($modified > $last_modified) {
                    $last_modified = $modified;
                }
            }
        }

        // Override the modified and ID so that it takes the latest change into account
        $page->modified($last_modified);
        $page->id($last_modified . md5($page->filePath()));

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
        $taxonomy = $this->grav['taxonomy'];

        // Get the home route
        $home = self::resetHomeRoute();

        // Build routes and taxonomy map.
        /** @var $page Page */
        foreach ($this->instances as $page) {
            if (!$page->root()) {
                // process taxonomy
                $taxonomy->addTaxonomy($page);

                $route = $page->route();
                $raw_route = $page->rawRoute();
                $page_path = $page->path();

                // add regular route
                $this->routes[$route] = $page_path;

                // add raw route
                if ($raw_route != $route) {
                    $this->routes[$raw_route] = $page_path;
                }

                // add canonical route
                $route_canonical = $page->routeCanonical();
                if ($route_canonical && ($route !== $route_canonical)) {
                    $this->routes[$route_canonical] = $page_path;
                }

                // add aliases to routes list if they are provided
                $route_aliases = $page->routeAliases();
                if ($route_aliases) {
                    foreach ($route_aliases as $alias) {
                        $this->routes[$alias] = $page_path;
                    }
                }
            }
        }

        // Alias and set default route to home page.
        if ($home && isset($this->routes['/' . $home])) {
            $this->routes['/'] = $this->routes['/' . $home];
            $this->get($this->routes['/' . $home])->route('/');
        }
    }

    /**
     * @param string $path
     * @param array  $pages
     * @param string $order_by
     * @param array  $manual
     *
     * @throws \RuntimeException
     * @internal
     */
    protected function buildSort($path, array $pages, $order_by = 'default', $manual = null, $sort_flags = null)
    {
        $list = [];
        $header_default = null;
        $header_query = null;

        // do this header query work only once
        if (strpos($order_by, 'header.') === 0) {
            $header_query = explode('|', str_replace('header.', '', $order_by));
            if (isset($header_query[1])) {
                $header_default = $header_query[1];
            }
        }

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
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'modified':
                    $list[$key] = $child->modified();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'publish_date':
                    $list[$key] = $child->publishDate();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'unpublish_date':
                    $list[$key] = $child->unpublishDate();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'slug':
                    $list[$key] = $child->slug();
                    break;
                case 'basename':
                    $list[$key] = basename($key);
                    break;
                case (is_string($header_query[0])):
                    $child_header = new Header((array)$child->header());
                    $header_value = $child_header->get($header_query[0]);
                    if (is_array($header_value)) {
                        $list[$key] = implode(',',$header_value);
                    } elseif ($header_value) {
                        $list[$key] = $header_value;
                    } else {
                        $list[$key] = $header_default ?: $key;
                    }
                    $sort_flags = $sort_flags ?: SORT_REGULAR;
                    break;
                case 'manual':
                case 'default':
                default:
                    $list[$key] = $key;
                    $sort_flags = $sort_flags ?: SORT_REGULAR;
            }
        }

        if (!$sort_flags) {
            $sort_flags = SORT_NATURAL | SORT_FLAG_CASE;
        }

        // handle special case when order_by is random
        if ($order_by == 'random') {
            $list = $this->arrayShuffle($list);
        } else {
            // else just sort the list according to specified key
            if (extension_loaded('intl')) {
                $locale = setlocale(LC_COLLATE, 0); //`setlocale` with a 0 param returns the current locale set
                $col = Collator::create($locale);
                if ($col) {
                    if (($sort_flags & SORT_NATURAL) === SORT_NATURAL) {
                        $list = preg_replace_callback('~([0-9]+)\.~', function($number) {
                            return sprintf('%032d.', $number[0]);
                        }, $list);
                    }

                    $col->asort($list, $sort_flags);
                } else {
                    asort($list, $sort_flags);
                }
            } else {
                asort($list, $sort_flags);
            }
        }


        // Move manually ordered items into the beginning of the list. Order of the unlisted items does not change.
        if (is_array($manual) && !empty($manual)) {
            $new_list = [];
            $i = count($manual);

            foreach ($list as $key => $dummy) {
                $info = $pages[$key];
                $order = array_search($info['slug'], $manual);
                if ($order === false) {
                    $order = $i++;
                }
                $new_list[$key] = (int)$order;
            }

            $list = $new_list;

            // Apply manual ordering to the list.
            asort($list);
        }

        foreach ($list as $key => $sort) {
            $info = $pages[$key];
            $this->sort[$path][$order_by][$key] = $info;
        }
    }

    /**
     * Shuffles an associative array
     *
     * @param array $list
     *
     * @return array
     */
    protected function arrayShuffle($list)
    {
        $keys = array_keys($list);
        shuffle($keys);

        $new = [];
        foreach ($keys as $key) {
            $new[$key] = $list[$key];
        }

        return $new;
    }

    /**
     * Get the Pages cache ID
     *
     * this is particularly useful to know if pages have changed and you want
     * to sync another cache with pages cache - works best in `onPagesInitialized()`
     *
     * @return mixed
     */
    public function getPagesCacheId()
    {
        return $this->pages_cache_id;
    }
}
