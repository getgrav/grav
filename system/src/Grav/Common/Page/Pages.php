<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
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
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Taxonomy;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Plugin\Admin;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Whoops\Exception\ErrorException;
use Collator;

class Pages
{
    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var array|PageInterface[]
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
    protected $baseRoute = [];

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

    /** @var string */
    protected $check_method;

    protected $pages_cache_id;

    protected $initialized = false;

    protected $active_lang;

    /**
     * @var Types
     */
    static protected $types;

    /**
     * @var string
     */
    static protected $home_route;

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
            $this->baseRoute = [];
        }

        return $this->base;
    }

    /**
     *
     * Get base route for Grav pages.
     *
     * @param  string $lang     Optional language code for multilingual routes.
     *
     * @return string
     */
    public function baseRoute($lang = null)
    {
        $key = $lang ?: $this->active_lang ?: 'default';

        if (!isset($this->baseRoute[$key])) {
            /** @var Language $language */
            $language = $this->grav['language'];

            $path_base = rtrim($this->base(), '/');
            $path_lang = $language->enabled() ? $language->getLanguageURLPrefix($lang) : '';

            $this->baseRoute[$key] = $path_base . $path_lang;
        }

        return $this->baseRoute[$key];
    }

    /**
     *
     * Get route for Grav site.
     *
     * @param  string $route    Optional route to the page.
     * @param  string $lang     Optional language code for multilingual links.
     *
     * @return string
     */
    public function route($route = '/', $lang = null)
    {
        if (!$route || $route === '/') {
            return $this->baseRoute($lang) ?: '/';
        }

        return $this->baseRoute($lang) . $route;
    }

    /**
     *
     * Get base URL for Grav pages.
     *
     * @param  string     $lang     Optional language code for multilingual links.
     * @param  bool|null  $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
     *
     * @return string
     */
    public function baseUrl($lang = null, $absolute = null)
    {
        if ($absolute === null) {
            $type = 'base_url';
        } elseif ($absolute) {
            $type = 'base_url_absolute';
        } else {
            $type = 'base_url_relative';
        }

        return $this->grav[$type] . $this->baseRoute($lang);
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
     * Get URL for Grav site.
     *
     * @param  string $route    Optional route to the page.
     * @param  string $lang     Optional language code for multilingual links.
     * @param  bool   $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
     *
     * @return string
     */
    public function url($route = '/', $lang = null, $absolute = null)
    {
        if (!$route || $route === '/') {
            return $this->homeUrl($lang, $absolute);
        }

        return $this->baseUrl($lang, $absolute) . Uri::filterPath($route);
    }

    public function setCheckMethod($method)
    {
        $this->check_method = strtolower($method);
    }

    /**
     * Reset pages (used in search indexing etc).
     */
    public function reset()
    {
        $this->initialized = false;

        $this->init();
    }

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

        if (!$this->check_method) {
            $this->setCheckMethod($config->get('system.cache.check.method', 'file'));
        }

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
     * @param PageInterface   $page  Page to be added.
     * @param string $route Optional route (uses route from the object if not set).
     */
    public function addPage(PageInterface $page, $route = null)
    {
        if (!isset($this->instances[$page->path()])) {
            $this->instances[$page->path()] = $page;
        }
        $route = $page->route($route);
        if ($page->parent()) {
            $this->children[$page->parent()->path()][$page->path()] = ['slug' => $page->slug()];
        }
        $this->routes[$route] = $page->path();

        $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
    }

    /**
     * Sort sub-pages in a page.
     *
     * @param PageInterface   $page
     * @param string $order_by
     * @param string $order_dir
     *
     * @return array
     */
    public function sort(PageInterface $page, $order_by = null, $order_dir = null, $sort_flags = null)
    {
        if ($order_by === null) {
            $order_by = $page->orderBy();
        }
        if ($order_dir === null) {
            $order_dir = $page->orderDir();
        }

        $path = $page->path();
        $children = $this->children[$path] ?? [];

        if (!$children) {
            return $children;
        }

        if (!isset($this->sort[$path][$order_by])) {
            $this->buildSort($path, $children, $order_by, $page->orderManual(), $sort_flags);
        }

        $sort = $this->sort[$path][$order_by];

        if ($order_dir !== 'asc') {
            $sort = array_reverse($sort);
        }

        return $sort;
    }

    /**
     * @param Collection $collection
     * @param string|int $orderBy
     * @param string     $orderDir
     * @param array|null $orderManual
     * @param int|null   $sort_flags
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

        if ($orderDir !== 'asc') {
            $sort = array_reverse($sort);
        }

        return $sort;

    }

    /**
     * Get a page instance.
     *
     * @param  string $path The filesystem full path of the page
     *
     * @return PageInterface
     * @throws \Exception
     */
    public function get($path)
    {
        return $this->instances[(string)$path] ?? null;
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
        $children = $this->children[(string)$path] ?? [];

        return new Collection($children, [], $this);
    }

    /**
     * Get a page ancestor.
     *
     * @param  string $route The relative URL of the page
     * @param  string $path The relative path of the ancestor folder
     *
     * @return PageInterface|null
     */
    public function ancestor($route, $path = null)
    {
        if ($path !== null) {
            $page = $this->dispatch($route, true);

            if ($page && $page->path() === $path) {
                return $page;
            }

            $parent = $page ? $page->parent() : null;
            if ($parent && !$parent->root()) {
                return $this->ancestor($parent->route(), $path);
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
     * @return PageInterface|null
     */
    public function inherited($route, $field = null)
    {
        if ($field !== null) {

            $page = $this->dispatch($route, true);

            $parent = $page ? $page->parent() : null;
            if ($parent && $parent->value('header.' . $field) !== null) {
                return $parent;
            }
            if ($parent && !$parent->root()) {
                return $this->inherited($parent->route(), $field);
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
     * @return PageInterface|null
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
     * @return PageInterface|null
     * @throws \Exception
     */
    public function dispatch($route, $all = false, $redirect = true)
    {
        $route = urldecode($route);

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

                    /** @var Uri $uri */
                    $uri = $this->grav['uri'];
                    /** @var \Grav\Framework\Uri\Uri $source_url */
                    $source_url = $uri->uri(false);

                    // Try Regex style redirects
                    $site_redirects = $config->get('site.redirects');
                    if (is_array($site_redirects)) {
                        foreach ((array)$site_redirects as $pattern => $replace) {
                            $pattern = '#^' . str_replace('/', '\/', ltrim($pattern, '^')) . '#';
                            try {
                                $found = preg_replace($pattern, $replace, $source_url);
                                if ($found !== $source_url) {
                                    $this->grav->redirectLangSafe($found);
                                }
                            } catch (ErrorException $e) {
                                $this->grav['log']->error('site.redirects: ' . $pattern . '-> ' . $e->getMessage());
                            }
                        }
                    }

                    // Try Regex style routes
                    $site_routes = $config->get('site.routes');
                    if (is_array($site_routes)) {
                        foreach ((array)$site_routes as $pattern => $replace) {
                            $pattern = '#^' . str_replace('/', '\/', ltrim($pattern, '^')) . '#';
                            try {
                                $found = preg_replace($pattern, $replace, $source_url);
                                if ($found !== $source_url) {
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
     * @return PageInterface
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
        if ($this->blueprints === null) {
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
     * @param PageInterface $current
     *
     * @return \Grav\Common\Page\Collection
     */
    public function all(PageInterface $current = null)
    {
        $all = new Collection();

        /** @var PageInterface $current */
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
     * @param PageInterface $current
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
    public function getList(PageInterface $current = null, $level = 0, $rawRoutes = false, $showAll = true, $showFullpath = false, $showSlug = false, $showModular = false, $limitLevels = false)
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

        if ($limitLevels === false || ($level+1 < $limitLevels)) {
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

            // Register custom paths to the locator.
            $locator = $grav['locator'];
            foreach (self::$types as $type => $paths) {
                foreach ($paths as $k => $path) {
                    if (strpos($path, 'blueprints://') === 0) {
                        unset($paths[$k]);
                    }
                }
                if ($paths) {
                    $locator->addPath('blueprints', "pages/$type.yaml", $paths);
                }
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

            /** @var PageInterface $page */
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
                if (\is_array($page->header()->access)) {
                    foreach ($page->header()->access as $index => $accessLevel) {
                        if (\is_array($accessLevel)) {
                            foreach ($accessLevel as $innerIndex => $innerAccessLevel) {
                                $accessLevels[] = $innerIndex;
                            }
                        } else {
                            $accessLevels[] = $index;
                        }
                    }
                } else {
                    $accessLevels[] = $page->header()->access;
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

        // Set active language
        $this->active_lang = $language->getActive();

        if ($config->get('system.cache.enabled')) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];

            // how should we check for last modified? Default is by file
            switch ($this->check_method) {
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

            $cached = $cache->fetch($this->pages_cache_id);
            if ($cached) {
                $this->grav['debugger']->addMessage('Page cache hit.');

                list($this->instances, $this->routes, $this->children, $taxonomy_map, $this->sort) = $cached;

                // If pages was found in cache, set the taxonomy
                $taxonomy->taxonomy($taxonomy_map);
            } else {
                $this->grav['debugger']->addMessage('Page cache missed, rebuilding pages..');

                // recurse pages and cache result
                $this->resetPages($pages_dir);
            }
        } else {
            $this->recurse($pages_dir);
            $this->buildRoutes();
        }
    }

    /**
     * Accessible method to manually reset the pages cache
     *
     * @param string $pages_dir
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
     * @param PageInterface|null $parent
     *
     * @return PageInterface
     * @throws \RuntimeException
     * @internal
     */
    protected function recurse($directory, PageInterface $parent = null)
    {
        $directory = rtrim($directory, DS);
        $page = new Page;

        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var Language $language */
        $language = $this->grav['language'];

        // Stuff to do at root page
        // Fire event for memory and time consuming plugins...
        if ($parent === null && $config->get('system.pages.events.page')) {
            $this->grav->fireEvent('onBuildPagesInitialized');
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

        // Build regular expression for all the allowed page extensions.
        $page_extensions = $language->getFallbackPageExtensions();
        $regex = '/^[^\.]*(' . implode('|', array_map(
            function ($str) {
                return preg_quote($str, '/');
            },
            $page_extensions
        )) . ')$/';

        $folders = [];
        $page_found = null;
        $page_extension = '.md';
        $last_modified = 0;

        $iterator = new \FilesystemIterator($directory);
        /** @var \FilesystemIterator $file */
        foreach ($iterator as $file) {
            $filename = $file->getFilename();

            // Ignore all hidden files if set.
            if ($this->ignore_hidden && $filename && strpos($filename, '.') === 0) {
                continue;
            }

            // Handle folders later.
            if ($file->isDir()) {
                // But ignore all folders in ignore list.
                if (!\in_array($filename, $this->ignore_folders, true)) {
                    $folders[] = $file;
                }
                continue;
            }

            // Ignore all files in ignore list.
            if (\in_array($filename, $this->ignore_files, true)) {
                continue;
            }

            // Update last modified date to match the last updated file in the folder.
            $modified = $file->getMTime();
            if ($modified > $last_modified) {
                $last_modified = $modified;
            }

            // Page is the one that matches to $page_extensions list with the lowest index number.
            if (preg_match($regex, $filename, $matches, PREG_OFFSET_CAPTURE)) {
                $ext = $matches[1][0];

                if ($page_found === null || array_search($ext, $page_extensions, true) < array_search($page_extension, $page_extensions, true)) {
                    $page_found = $file;
                    $page_extension = $ext;
                }
            }
        }

        $content_exists = false;
        if ($parent && $page_found) {
            $page->init($page_found, $page_extension);

            $content_exists = true;

            if ($config->get('system.pages.events.page')) {
                $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
            }
        }

        // Now handle all the folders under the page.
        /** @var \FilesystemIterator $file */
        foreach ($folders as $file) {
            $filename = $file->getFilename();

            // if folder contains separator, continue
            if (Utils::contains($file->getFilename(), $config->get('system.param_sep', ':'))) {
                continue;
            }

            if (!$page->path()) {
                $page->path($file->getPath());
            }

            $path = $directory . DS . $filename;
            $child = $this->recurse($path, $page);

            if (Utils::startsWith($filename, '_')) {
                $child->routable(false);
            }

            $this->children[$page->path()][$child->path()] = ['slug' => $child->slug()];

            if ($config->get('system.pages.events.page')) {
                $this->grav->fireEvent('onFolderProcessed', new Event(['page' => $page]));
            }
        }


        if (!$content_exists) {
            // Set routability to false if no page found
            $page->routable(false);

            // Hide empty folders if option set
            if ($config->get('system.pages.hide_empty_folders')) {
                $page->visible(false);
            }
        }

        // Override the modified time if modular
        if ($page->template() === 'modular') {
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
        /** @var Taxonomy $taxonomy */
        $taxonomy = $this->grav['taxonomy'];

        // Get the home route
        $home = self::resetHomeRoute();

        // Build routes and taxonomy map.
        /** @var PageInterface $page */
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
                if ($raw_route !== $route) {
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
        $homeRoute = '/' . $home;
        if ($home && isset($this->routes[$homeRoute])) {
            $this->routes['/'] = $this->routes[$homeRoute];
            $this->get($this->routes[$homeRoute])->route('/');
        }
    }

    /**
     * @param string $path
     * @param array  $pages
     * @param string $order_by
     * @param array|null  $manual
     * @param int|null    $sort_flags
     *
     * @throws \RuntimeException
     * @internal
     */
    protected function buildSort($path, array $pages, $order_by = 'default', $manual = null, $sort_flags = null)
    {
        $list = [];
        $header_default = null;
        $header_query = [];

        // do this header query work only once
        if (strpos($order_by, 'header.') === 0) {
            $query = explode('|', str_replace('header.', '', $order_by), 2);
            $header_query = array_shift($query) ?? '';
            $header_default = array_shift($query);
        }

        foreach ($pages as $key => $info) {
            $child = $this->instances[$key] ?? null;
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
                case 'folder':
                    $list[$key] = $child->folder();
                    break;
                case 'manual':
                case 'default':
                default:
                if (is_string($header_query)) {
                    $child_header = $child->header();
                    if (!$child_header instanceof Header) {
                        $child_header = new Header((array)$child_header);
                    }
                    $header_value = $child_header->get($header_query);
                    if (is_array($header_value)) {
                        $list[$key] = implode(',', $header_value);
                    } elseif ($header_value) {
                        $list[$key] = $header_value;
                    } else {
                        $list[$key] = $header_default ?: $key;
                    }
                    $sort_flags = $sort_flags ?: SORT_REGULAR;
                    break;
                }
                $list[$key] = $key;
                $sort_flags = $sort_flags ?: SORT_REGULAR;
            }
        }

        if (!$sort_flags) {
            $sort_flags = SORT_NATURAL | SORT_FLAG_CASE;
        }

        // handle special case when order_by is random
        if ($order_by === 'random') {
            $list = $this->arrayShuffle($list);
        } else {
            // else just sort the list according to specified key
            if (extension_loaded('intl') && $this->grav['config']->get('system.intl_enabled')) {
                $locale = setlocale(LC_COLLATE, 0); //`setlocale` with a 0 param returns the current locale set
                $col = Collator::create($locale);
                if ($col) {
                    if (($sort_flags & SORT_NATURAL) === SORT_NATURAL) {
                        $list = preg_replace_callback('~([0-9]+)\.~', function($number) {
                            return sprintf('%032d.', $number[0]);
                        }, $list);

                        $list_vals = array_values($list);
                        if (is_numeric(array_shift($list_vals))) {
                            $sort_flags = Collator::SORT_REGULAR;
                        } else {
                            $sort_flags = Collator::SORT_STRING;
                        }
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
                $order = \array_search($info['slug'], $manual, true);
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
