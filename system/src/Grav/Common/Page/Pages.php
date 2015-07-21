<?php
namespace Grav\Common\Page;

use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\Utils;
use Grav\Common\Cache;
use Grav\Common\Taxonomy;
use Grav\Common\Language;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Blueprints;
use Grav\Common\Filesystem\Folder;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Whoops\Exception\ErrorException;

/**
 * GravPages is the class that is the entry point into the hierarchy of pages
 *
 * @author RocketTheme
 * @license MIT
 */
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
    protected $base;

    /**
     * @var array|string[]
     */
    protected $routes = array();

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
     * @var Types
     */
    static protected $types;

    static protected $home_route;

    /**
     * Constructor
     *
     * @param Grav $c
     */
    public function __construct(Grav $c)
    {
        $this->grav = $c;
        $this->base = '';
    }

    /**
     * Get or set base path for the pages.
     *
     * @param  string $path
     * @return string
     */
    public function base($path = null)
    {
        if ($path !== null) {
            $path = trim($path, '/');
            $this->base = $path ? '/' . $path : null;
        }

        return $this->base;
    }

    /**
     * Class initialization. Must be called before using this class.
     */
    public function init()
    {
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
        if (!$items) {
            return [];
        }

        $lookup = md5(json_encode($items));
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
     * @throws \Exception
     */
    public function get($path)
    {
        if (!is_null($path) && !is_string($path)) {
            throw new \Exception();
        }
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

        // If the page cannot be reached, look into site wide redirects, routes + wildcards
        if (!$all && (!$page || !$page->routable())) {
            /** @var Config $config */
            $config = $this->grav['config'];

            // See if route matches one in the site configuration
            $route = $config->get("site.routes.{$url}");
            if ($route) {
                $page = $this->dispatch($route, $all);
            } else {
                // Try Regex style redirects
                foreach ((array)$config->get("site.redirects") as $pattern => $replace) {
                    $pattern = '#' . $pattern . '#';
                    try {
                        $found = preg_replace($pattern, $replace, $url);
                        if ($found != $url) {
                            $this->grav->redirectLangSafe($found);
                        }
                    } catch (ErrorException $e) {
                        $this->grav['log']->error('site.redirects: '. $pattern . '-> ' . $e->getMessage());
                    }
                }

                // Try Regex style routes
                foreach ((array)$config->get("site.routes") as $pattern => $replace) {
                    $pattern = '#' . $pattern . '#';
                    try {
                        $found = preg_replace($pattern, $replace, $url);
                        if ($found != $url) {
                            $page = $this->dispatch($found, $all);
                        }
                    } catch (ErrorException $e) {
                        $this->grav['log']->error('site.routes: '. $pattern . '-> ' . $e->getMessage());
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
     * @param  string  $type
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

        if (!$blueprint->initialized) {
            $this->grav->fireEvent('onBlueprintCreated', new Event(['blueprint' => $blueprint]));
            $blueprint->initialized = true;
        }

        return $blueprint;
    }

    /**
     * Get all pages
     *
     * @param \Grav\Common\Page\Page $current
     * @return \Grav\Common\Page\Collection
     */
    public function all(Page $current = null)
    {
        $all = new Collection();
        $current = $current ?: $this->root();

        if ($current->routable()) {
            $all[$current->path()] = [ 'slug' => $current->slug() ];
        }

        foreach ($current->children() as $next) {
            $all->append($this->all($next));
        }

        return $all;
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

        foreach ($current->children() as $next) {
            $list = array_merge($list, $this->getList($next, $level + 1));
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
            self::$types = new Types();
            self::$types->scanBlueprints('theme://blueprints/');
            self::$types->scanTemplates('theme://templates/');

            $event = new Event();
            $event->types = self::$types;
            Grav::instance()->fireEvent('onGetPageTemplates', $event);
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
     * Get available parents.
     *
     * @return array
     */
    public static function parents()
    {
        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        return $pages->getList();
    }

    /**
     * Get's the home route
     *
     * @return string
     */
    public static function getHomeRoute()
    {
        if (empty(self::$home)) {
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
     * Builds pages.
     *
     * @internal
     */
    protected function buildPages()
    {
        $this->sort = array();

        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var Language $language */
        $language = $this->grav['language'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $pagesDir = $locator->findResource('page://');

        if ($config->get('system.cache.enabled')) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];

            // how should we check for last modified? Default is by file
            switch (strtolower($config->get('system.cache.check.method', 'file'))) {
                case 'none':
                case 'off':
                    $last_modified = 0;
                    break;
                case 'folder':
                    $last_modified = Folder::lastModifiedFolder($pagesDir);
                    break;
                default:
                    $last_modified = Folder::lastModifiedFile($pagesDir);
            }

            $page_cache_id = md5(USER_DIR.$last_modified.$language->getActive().$config->checksum());

            list($this->instances, $this->routes, $this->children, $taxonomy_map, $this->sort) = $cache->fetch($page_cache_id);
            if (!$this->instances) {
                $this->grav['debugger']->addMessage('Page cache missed, rebuilding pages..');

                $this->recurse($pagesDir);
                $this->buildRoutes();

                // save pages, routes, taxonomy, and sort to cache
                $cache->save(
                    $page_cache_id,
                    array($this->instances, $this->routes, $this->children, $taxonomy->taxonomy(), $this->sort)
                );
            } else {
                // If pages was found in cache, set the taxonomy
                $this->grav['debugger']->addMessage('Page cache hit.');
                $taxonomy->taxonomy($taxonomy_map);
            }
        } else {
            $this->recurse($pagesDir);
            $this->buildRoutes();
        }
    }

    /**
     * Recursive function to load & build page relationships.
     *
     * @param string $directory
     * @param Page|null $parent
     * @return Page
     * @throws \RuntimeException
     * @internal
     */
    protected function recurse($directory, Page &$parent = null)
    {
        $directory  = rtrim($directory, DS);
        $page       = new Page;

        /** @var Config $config */
        $config     = $this->grav['config'];

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
                $this->children[$parent->path()][$page->path()] = array('slug' => $page->slug());
            }
        } else {
            throw new \RuntimeException('Fatal error when creating page instances.');
        }

        $content_exists = false;
        $pages_found = glob($directory.'/*'.CONTENT_EXT);
        $page_extensions = $language->getFallbackPageExtensions();

        if ($pages_found) {
            foreach ($page_extensions as $extension) {
                foreach ($pages_found as $found) {
                    if (preg_match('/^.*\/[0-9A-Za-z\-\_]+('.$extension.')$/', $found)) {
                        $page_found = $found;
                        $page_extension = $extension;
                        break 2;
                    }
                }
            }
        }

        if ($parent && !empty($page_found)) {
            $file = new \SplFileInfo($page_found);
            $page->init($file, $page_extension);

            $content_exists = true;

            if ($config->get('system.pages.events.page')) {
                $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
            }
        }

        // set current modified of page
        $last_modified = $page->modified();

        /** @var \DirectoryIterator $file */
        foreach (new \FilesystemIterator($directory) as $file) {
            $name = $file->getFilename();

            if ($file->isFile()) {
                // Update the last modified if it's newer than already found
                if ($file->getBasename() !== '.DS_Store' && ($modified = $file->getMTime()) > $last_modified) {
                    $last_modified = $modified;
                }
            } elseif ($file->isDir()) {
                if (!$page->path()) {
                    $page->path($file->getPath());
                }

                $path = $directory.DS.$name;
                $child = $this->recurse($path, $page);

                if (Utils::startsWith($name, '_')) {
                    $child->routable(false);
                }

                $this->children[$page->path()][$child->path()] = array('slug' => $child->slug());

                if ($config->get('system.pages.events.page')) {
                    $this->grav->fireEvent('onFolderProcessed', new Event(['page' => $page]));
                }
            }
        }

        // Set routability to false if no page found
        if (!$content_exists) {
            $page->routable(false);
        }

        // Override the modified and ID so that it takes the latest change into account
        $page->modified($last_modified);
        $page->id($last_modified.md5($page->filePath()));

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
        $home = self::getHomeRoute();

        // Build routes and taxonomy map.
        /** @var $page Page */
        foreach ($this->instances as $page) {
            if (!$page->root()) {
                // process taxonomy
                $taxonomy->addTaxonomy($page);

                $route =  $page->route();
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
     * @param array $pages
     * @param string $order_by
     * @param array $manual
     * @throws \RuntimeException
     * @internal
     */
    protected function buildSort($path, array $pages, $order_by = 'default', $manual = null)
    {
        $list = array();
        $header_default = null;
        $header_query = null;

        // do this headery query work only once
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
                    break;
                case 'modified':
                    $list[$key] = $child->modified();
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
                    if ($header_value) {
                        $list[$key] = $header_value;
                    } else {
                        $list[$key] = $header_default ?: $key;
                    }
                    break;
                case 'manual':
                case 'default':
                default:
                    $list[$key] = $key;
            }
        }

        // handle special case when order_by is random
        if ($order_by == 'random') {
            $list = $this->arrayShuffle($list);
        } else {
            // else just sort the list according to specified key
            asort($list);
        }


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

    // Shuffles and associative array
    protected function arrayShuffle($list)
    {
        $keys = array_keys($list);
        shuffle($keys);

        $new = array();
        foreach ($keys as $key) {
            $new[$key] = $list[$key];
        }

        return $new;
    }
}
