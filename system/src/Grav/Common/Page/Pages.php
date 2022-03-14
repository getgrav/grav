<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Exception;
use FilesystemIterator;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Blueprints;
use Grav\Common\Debugger;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageCollection;
use Grav\Common\Flex\Types\Pages\PageIndex;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Taxonomy;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexTranslateInterface;
use Grav\Framework\Flex\Pages\FlexPageObject;
use Grav\Plugin\Admin;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Whoops\Exception\ErrorException;
use Collator;
use function array_key_exists;
use function array_search;
use function count;
use function dirname;
use function extension_loaded;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Class Pages
 * @package Grav\Common\Page
 */
class Pages
{
    /** @var FlexDirectory|null */
    private $directory;

    /** @var Grav */
    protected $grav;
    /** @var array<PageInterface> */
    protected $instances = [];
    /** @var array<PageInterface|string> */
    protected $index = [];
    /** @var array */
    protected $children;
    /** @var string */
    protected $base = '';
    /** @var string[] */
    protected $baseRoute = [];
    /** @var string[] */
    protected $routes = [];
    /** @var array */
    protected $sort;
    /** @var Blueprints */
    protected $blueprints;
    /** @var bool */
    protected $enable_pages = true;
    /** @var int */
    protected $last_modified;
    /** @var string[] */
    protected $ignore_files;
    /** @var string[] */
    protected $ignore_folders;
    /** @var bool */
    protected $ignore_hidden;
    /** @var string */
    protected $check_method;
    /** @var string */
    protected $pages_cache_id;
    /** @var bool */
    protected $initialized = false;
    /** @var string */
    protected $active_lang;
    /** @var bool */
    protected $fire_events = false;
    /** @var Types|null */
    protected static $types;
    /** @var string|null */
    protected static $home_route;

    /**
     * Constructor
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /**
     * @return FlexDirectory|null
     */
    public function getDirectory(): ?FlexDirectory
    {
        return $this->directory;
    }

    /**
     * Method used in admin to disable frontend pages from being initialized.
     */
    public function disablePages(): void
    {
        $this->enable_pages = false;
    }

    /**
     * Method used in admin to later load frontend pages.
     */
    public function enablePages(): void
    {
        if (!$this->enable_pages) {
            $this->enable_pages = true;

            $this->init();
        }
    }

    /**
     * Get or set base path for the pages.
     *
     * @param  string|null $path
     * @return string
     */
    public function base($path = null)
    {
        if ($path !== null) {
            $path = trim($path, '/');
            $this->base = $path ? '/' . $path : '';
            $this->baseRoute = [];
        }

        return $this->base;
    }

    /**
     *
     * Get base route for Grav pages.
     *
     * @param  string|null $lang     Optional language code for multilingual routes.
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
     * @param  string|null $lang     Optional language code for multilingual links.
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
     * Get relative referrer route and language code. Returns null if the route isn't within the current base, language (if set) and route.
     *
     * @example `$langCode = null; $referrer = $pages->referrerRoute($langCode, '/admin');` returns relative referrer url within /admin and updates $langCode
     * @example `$langCode = 'en'; $referrer = $pages->referrerRoute($langCode, '/admin');` returns relative referrer url within the /en/admin
     *
     * @param string|null $langCode Variable to store the language code. If already set, check only against that language.
     * @param string $route Optional route within the site.
     * @return string|null
     * @since 1.7.23
     */
    public function referrerRoute(?string &$langCode, string $route = '/'): ?string
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;

        // Start by checking that referrer came from our site.
        $root = $this->grav['base_url_absolute'];
        if (!is_string($referrer) || !str_starts_with($referrer, $root)) {
            return null;
        }

        /** @var Language $language */
        $language = $this->grav['language'];

        // Get all language codes and append no language.
        if (null === $langCode) {
            $languages = $language->enabled() ? $language->getLanguages() : [];
            $languages[] = '';
        } else {
            $languages[] = $langCode;
        }

        $path_base = rtrim($this->base(), '/');
        $path_route = rtrim($route, '/');

        // Try to figure out the language code.
        foreach ($languages as $code) {
            $path_lang = $code ? "/{$code}" : '';

            $base = $path_base . $path_lang . $path_route;
            if ($referrer === $base || str_starts_with($referrer, "{$base}/")) {
                if (null === $langCode) {
                    $langCode = $code;
                }

                return substr($referrer, \strlen($base));
            }
        }

        return null;
    }

    /**
     *
     * Get base URL for Grav pages.
     *
     * @param  string|null $lang     Optional language code for multilingual links.
     * @param  bool|null  $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
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
     * @param  string|null $lang     Optional language code for multilingual links.
     * @param  bool|null   $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
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
     * @param  string|null $lang     Optional language code for multilingual links.
     * @param  bool|null   $absolute If true, return absolute url, if false, return relative url. Otherwise return default.
     * @return string
     */
    public function url($route = '/', $lang = null, $absolute = null)
    {
        if (!$route || $route === '/') {
            return $this->homeUrl($lang, $absolute);
        }

        return $this->baseUrl($lang, $absolute) . Uri::filterPath($route);
    }

    /**
     * @param string $method
     * @return void
     */
    public function setCheckMethod($method): void
    {
        $this->check_method = strtolower($method);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $config = $this->grav['config'];
        $type = $config->get('system.pages.type');
        if ($type === 'flex') {
            $this->initFlexPages();
        }
    }

    /**
     * Reset pages (used in search indexing etc).
     *
     * @return void
     */
    public function reset(): void
    {
        $this->initialized = false;

        $this->init();
    }

    /**
     * Class initialization. Must be called before using this class.
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $config = $this->grav['config'];
        $this->ignore_files = (array)$config->get('system.pages.ignore_files');
        $this->ignore_folders = (array)$config->get('system.pages.ignore_folders');
        $this->ignore_hidden = (bool)$config->get('system.pages.ignore_hidden');
        $this->fire_events = (bool)$config->get('system.pages.events.page');

        $this->instances = [];
        $this->index = [];
        $this->children = [];
        $this->routes = [];

        if (!$this->check_method) {
            $this->setCheckMethod($config->get('system.cache.check.method', 'file'));
        }

        if ($this->enable_pages === false) {
            $page = $this->buildRootPage();
            $this->instances[$page->path()] = $page;

            return;
        }

        $this->buildPages();

        $this->initialized = true;
    }

    /**
     * Get or set last modification time.
     *
     * @param int|null $modified
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
     * @return PageInterface[]
     */
    public function instances()
    {
        $instances = [];
        foreach ($this->index as $path => $instance) {
            $page = $this->get($path);
            if ($page) {
                $instances[$path] = $page;
            }
        }

        return $instances;
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
     * @param string|null $route Optional route (uses route from the object if not set).
     */
    public function addPage(PageInterface $page, $route = null): void
    {
        $path = $page->path() ?? '';
        if (!isset($this->index[$path])) {
            $this->index[$path] = $page;
            $this->instances[$path] = $page;
        }
        $route = $page->route($route);
        $parent = $page->parent();
        if ($parent) {
            $this->children[$parent->path() ?? ''][$path] = ['slug' => $page->slug()];
        }
        $this->routes[$route] = $path;

        $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
    }

    /**
     * Get a collection of pages in the given context.
     *
     * @param array $params
     * @param array $context
     * @return PageCollectionInterface|Collection
     */
    public function getCollection(array $params = [], array $context = [])
    {
        if (!isset($params['items'])) {
            return new Collection();
        }

        /** @var Config $config */
        $config = $this->grav['config'];

        $context += [
            'event' => true,
            'pagination' => true,
            'url_taxonomy_filters' => $config->get('system.pages.url_taxonomy_filters'),
            'taxonomies' => (array)$config->get('site.taxonomies'),
            'pagination_page' => 1,
            'self' => null,
        ];

        // Include taxonomies from the URL if requested.
        $process_taxonomy = $params['url_taxonomy_filters'] ?? $context['url_taxonomy_filters'];
        if ($process_taxonomy) {
            /** @var Uri $uri */
            $uri = $this->grav['uri'];
            foreach ($context['taxonomies'] as $taxonomy) {
                $param = $uri->param(rawurlencode($taxonomy));
                $items = is_string($param) ? explode(',', $param) : [];
                foreach ($items as $item) {
                    $params['taxonomies'][$taxonomy][] = htmlspecialchars_decode(rawurldecode($item), ENT_QUOTES);
                }
            }
        }

        $pagination = $params['pagination'] ?? $context['pagination'];
        if ($pagination && !isset($params['page'], $params['start'])) {
            /** @var Uri $uri */
            $uri = $this->grav['uri'];
            $context['current_page'] = $uri->currentPage();
        }

        $collection = $this->evaluate($params['items'], $context['self']);
        $collection->setParams($params);

        // Filter by taxonomies.
        foreach ($params['taxonomies'] ?? [] as $taxonomy => $items) {
            foreach ($collection as $page) {
                // Don't include modules
                if ($page->isModule()) {
                    continue;
                }

                $test = $page->taxonomy()[$taxonomy] ?? [];
                foreach ($items as $item) {
                    if (!$test || !in_array($item, $test, true)) {
                        $collection->remove($page->path());
                    }
                }
            }
        }

        $filters = $params['filter'] ?? [];

        // Assume published=true if not set.
        if (!isset($filters['published']) && !isset($filters['non-published'])) {
            $filters['published'] = true;
        }

        // Remove any inclusive sets from filter.
        $sets = ['published', 'visible', 'modular', 'routable'];
        foreach ($sets as $type) {
            $nonType = "non-{$type}";
            if (isset($filters[$type], $filters[$nonType]) && $filters[$type] === $filters[$nonType]) {
                if (!$filters[$type]) {
                    // Both options are false, return empty collection as nothing can match the filters.
                    return new Collection();
                }

                // Both options are true, remove opposite filters as all pages will match the filters.
                unset($filters[$type], $filters[$nonType]);
            }
        }

        // Filter the collection
        foreach ($filters as $type => $filter) {
            if (null === $filter) {
                continue;
            }

            // Convert non-type to type.
            if (str_starts_with($type, 'non-')) {
                $type = substr($type, 4);
                $filter = !$filter;
            }

            switch ($type) {
                case 'translated':
                    if ($filter) {
                        $collection = $collection->translated();
                    } else {
                        $collection = $collection->nonTranslated();
                    }
                    break;
                case 'published':
                    if ($filter) {
                        $collection = $collection->published();
                    } else {
                        $collection = $collection->nonPublished();
                    }
                    break;
                case 'visible':
                    if ($filter) {
                        $collection = $collection->visible();
                    } else {
                        $collection = $collection->nonVisible();
                    }
                    break;
                case 'page':
                    if ($filter) {
                        $collection = $collection->pages();
                    } else {
                        $collection = $collection->modules();
                    }
                    break;
                case 'module':
                case 'modular':
                    if ($filter) {
                        $collection = $collection->modules();
                    } else {
                        $collection = $collection->pages();
                    }
                    break;
                case 'routable':
                    if ($filter) {
                        $collection = $collection->routable();
                    } else {
                        $collection = $collection->nonRoutable();
                    }
                    break;
                case 'type':
                    $collection = $collection->ofType($filter);
                    break;
                case 'types':
                    $collection = $collection->ofOneOfTheseTypes($filter);
                    break;
                case 'access':
                    $collection = $collection->ofOneOfTheseAccessLevels($filter);
                    break;
            }
        }

        if (isset($params['dateRange'])) {
            $start = $params['dateRange']['start'] ?? null;
            $end = $params['dateRange']['end'] ?? null;
            $field = $params['dateRange']['field'] ?? null;
            $collection = $collection->dateRange($start, $end, $field);
        }

        if (isset($params['order'])) {
            $by = $params['order']['by'] ?? 'default';
            $dir = $params['order']['dir'] ?? 'asc';
            $custom = $params['order']['custom'] ?? null;
            $sort_flags = $params['order']['sort_flags'] ?? null;

            if (is_array($sort_flags)) {
                $sort_flags = array_map('constant', $sort_flags); //transform strings to constant value
                $sort_flags = array_reduce($sort_flags, static function ($a, $b) {
                    return $a | $b;
                }, 0); //merge constant values using bit or
            }

            $collection = $collection->order($by, $dir, $custom, $sort_flags);
        }

        // New Custom event to handle things like pagination.
        if ($context['event']) {
            $this->grav->fireEvent('onCollectionProcessed', new Event(['collection' => $collection, 'context' => $context]));
        }

        if ($context['pagination']) {
            // Slice and dice the collection if pagination is required
            $params = $collection->params();

            $limit = (int)($params['limit'] ?? 0);
            $page = (int)($params['page'] ?? $context['current_page'] ?? 0);
            $start = (int)($params['start'] ?? 0);
            $start = $limit > 0 && $page > 0 ? ($page - 1) * $limit : max(0, $start);

            if ($start || ($limit && $collection->count() > $limit)) {
                $collection->slice($start, $limit ?: null);
            }
        }

        return $collection;
    }

    /**
     * @param array|string $value
     * @param PageInterface|null $self
     * @return Collection
     */
    protected function evaluate($value, PageInterface $self = null)
    {
        // Parse command.
        if (is_string($value)) {
            // Format: @command.param
            $cmd = $value;
            $params = [];
        } elseif (is_array($value) && count($value) === 1 && !is_int(key($value))) {
            // Format: @command.param: { attr1: value1, attr2: value2 }
            $cmd = (string)key($value);
            $params = (array)current($value);
        } else {
            $result = [];
            foreach ((array)$value as $key => $val) {
                if (is_int($key)) {
                    $result = $result + $this->evaluate($val, $self)->toArray();
                } else {
                    $result = $result + $this->evaluate([$key => $val], $self)->toArray();
                }
            }

            return new Collection($result);
        }

        $parts = explode('.', $cmd);
        $scope = array_shift($parts);
        $type = $parts[0] ?? null;

        /** @var PageInterface|null $page */
        $page = null;
        switch ($scope) {
            case 'self@':
            case '@self':
                $page = $self;
                break;

            case 'page@':
            case '@page':
                $page = isset($params[0]) ? $this->find($params[0]) : null;
                break;

            case 'root@':
            case '@root':
                $page = $this->root();
                break;

            case 'taxonomy@':
            case '@taxonomy':
                // Gets a collection of pages by using one of the following formats:
                // @taxonomy.category: blog
                // @taxonomy.category: [ blog, featured ]
                // @taxonomy: { category: [ blog, featured ], level: 1 }

                /** @var Taxonomy $taxonomy_map */
                $taxonomy_map = Grav::instance()['taxonomy'];

                if (!empty($parts)) {
                    $params = [implode('.', $parts) => $params];
                }

                return $taxonomy_map->findTaxonomy($params);
        }

        if (!$page) {
            return new Collection();
        }

        // Handle '@page', '@page.modular: false', '@self' and '@self.modular: false'.
        if (null === $type || (in_array($type, ['modular', 'modules']) && ($params[0] ?? null) === false)) {
            $type = 'children';
        }

        switch ($type) {
            case 'all':
                $collection = $page->children();
                break;
            case 'modules':
            case 'modular':
                $collection = $page->children()->modules();
                break;
            case 'pages':
            case 'children':
                $collection = $page->children()->pages();
                break;
            case 'page':
            case 'self':
                $collection = !$page->root() ? (new Collection())->addPage($page) : new Collection();
                break;
            case 'parent':
                $parent = $page->parent();
                $collection = new Collection();
                $collection = $parent ? $collection->addPage($parent) : $collection;
                break;
            case 'siblings':
                $parent = $page->parent();
                if ($parent) {
                    /** @var Collection $collection */
                    $collection = $parent->children();
                    $collection = $collection->remove($page->path());
                } else {
                    $collection = new Collection();
                }
                break;
            case 'descendants':
                $collection = $this->all($page)->remove($page->path())->pages();
                break;
            default:
                // Unknown type; return empty collection.
                $collection = new Collection();
                break;
        }

        return $collection;
    }

    /**
     * Sort sub-pages in a page.
     *
     * @param PageInterface   $page
     * @param string|null $order_by
     * @param string|null $order_dir
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
        if (null === $path) {
            return [];
        }

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
     * @param string     $orderBy
     * @param string     $orderDir
     * @param array|null $orderManual
     * @param int|null   $sort_flags
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
     * @return PageInterface|null
     * @throws RuntimeException
     */
    public function get($path)
    {
        $path = (string)$path;
        if ($path === '') {
            return null;
        }

        // Check for local instances first.
        if (array_key_exists($path, $this->instances)) {
            return $this->instances[$path];
        }

        $instance = $this->index[$path] ?? null;
        if (is_string($instance)) {
            if ($this->directory) {
                /** @var Language $language */
                $language = $this->grav['language'];
                $lang = $language->getActive();
                if ($lang) {
                    $languages = $language->getFallbackLanguages($lang, true);
                    $key = $instance;
                    $instance = null;
                    foreach ($languages as $code) {
                        $test = $code ? $key . ':' . $code : $key;
                        if (($instance = $this->directory->getObject($test, 'flex_key')) !== null) {
                            break;
                        }
                    }
                } else {
                    $instance = $this->directory->getObject($instance, 'flex_key');
                }
            }

            if ($instance instanceof PageInterface) {
                if ($this->fire_events && method_exists($instance, 'initialize')) {
                    $instance->initialize();
                }
            } else {
                /** @var Debugger $debugger */
                $debugger = $this->grav['debugger'];
                $debugger->addMessage(sprintf('Flex page %s is missing or broken!', $instance), 'debug');
            }
        }

        if ($instance) {
            $this->instances[$path] = $instance;
        }

        return $instance;
    }

    /**
     * Get children of the path.
     *
     * @param string $path
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
     * @param  string|null $path The relative path of the ancestor folder
     * @return PageInterface|null
     */
    public function ancestor($route, $path = null)
    {
        if ($path !== null) {
            $page = $this->find($route, true);

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
     * @param  string|null $field The field name of the ancestor to query for
     * @return PageInterface|null
     */
    public function inherited($route, $field = null)
    {
        if ($field !== null) {
            $page = $this->find($route, true);

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
     * Find a page based on route.
     *
     * @param string $route The route of the page
     * @param bool   $all   If true, return also non-routable pages, otherwise return null if page isn't routable
     * @return PageInterface|null
     */
    public function find($route, $all = false)
    {
        $route = urldecode((string)$route);

        // Fetch page if there's a defined route to it.
        $path = $this->routes[$route] ?? null;
        $page = null !== $path ? $this->get($path) : null;

        // Try without trailing slash
        if (null === $page && Utils::endsWith($route, '/')) {
            $path = $this->routes[rtrim($route, '/')] ?? null;
            $page = null !== $path ? $this->get($path) : null;
        }

        if (!$all && !isset($this->grav['admin'])) {
            if (null === $page || !$page->routable()) {
                // If the page cannot be accessed, look for the site wide routes and wildcards.
                $page = $this->findSiteBasedRoute($route) ?? $page;
            }
        }

        return $page;
    }

    /**
     * Check site based routes.
     *
     * @param string $route
     * @return PageInterface|null
     */
    protected function findSiteBasedRoute($route)
    {
        /** @var Config $config */
        $config = $this->grav['config'];

        $site_routes = $config->get('site.routes');
        if (!is_array($site_routes)) {
            return null;
        }

        $page = null;

        // See if route matches one in the site configuration
        $site_route = $site_routes[$route] ?? null;
        if ($site_route) {
            $page = $this->find($site_route);
        } else {
            // Use reverse order because of B/C (previously matched multiple and returned the last match).
            foreach (array_reverse($site_routes, true) as $pattern => $replace) {
                $pattern = '#^' . str_replace('/', '\/', ltrim($pattern, '^')) . '#';
                try {
                    $found = preg_replace($pattern, $replace, $route);
                    if ($found && $found !== $route) {
                        $page = $this->find($found);
                        if ($page) {
                            return $page;
                        }
                    }
                } catch (ErrorException $e) {
                    $this->grav['log']->error('site.routes: ' . $pattern . '-> ' . $e->getMessage());
                }
            }
        }

        return $page;
    }

    /**
     * Dispatch URI to a page.
     *
     * @param string $route The relative URL of the page
     * @param bool $all If true, return also non-routable pages, otherwise return null if page isn't routable
     * @param bool $redirect If true, allow redirects
     * @return PageInterface|null
     * @throws Exception
     */
    public function dispatch($route, $all = false, $redirect = true)
    {
        $page = $this->find($route, true);

        // If we want all pages or are in admin, return what we already have.
        if ($all || isset($this->grav['admin'])) {
            return $page;
        }

        if ($page) {
            $routable = $page->routable();
            if ($redirect) {
                if ($page->redirect()) {
                    // Follow a redirect page.
                    $this->grav->redirectLangSafe($page->redirect());
                }

                if (!$routable) {
                    /** @var Collection $children */
                    $children = $page->children()->visible()->routable()->published();
                    $child = $children->first();
                    if ($child !== null) {
                        // Redirect to the first visible child as current page isn't routable.
                        $this->grav->redirectLangSafe($child->route());
                    }
                }
            }

            if ($routable) {
                return $page;
            }
        }

        $route = urldecode((string)$route);

        // The page cannot be reached, look into site wide redirects, routes and wildcards.
        $redirectedPage = $this->findSiteBasedRoute($route);
        if ($redirectedPage) {
            $page = $this->dispatch($redirectedPage->route(), false, $redirect);
        }

        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        /** @var \Grav\Framework\Uri\Uri $source_url */
        $source_url = $uri->uri(false);

        // Try Regex style redirects
        $site_redirects = $config->get('site.redirects');
        if (is_array($site_redirects)) {
            foreach ((array)$site_redirects as $pattern => $replace) {
                $pattern = ltrim($pattern, '^');
                $pattern = '#^' . str_replace('/', '\/', $pattern) . '#';
                try {
                    /** @var string $found */
                    $found = preg_replace($pattern, $replace, $source_url);
                    if ($found && $found !== $source_url) {
                        $this->grav->redirectLangSafe($found);
                    }
                } catch (ErrorException $e) {
                    $this->grav['log']->error('site.redirects: ' . $pattern . '-> ' . $e->getMessage());
                }
            }
        }

        return $page;
    }

    /**
     * Get root page.
     *
     * @return PageInterface
     * @throws RuntimeException
     */
    public function root()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $path = $locator->findResource('page://');
        $root = is_string($path) ? $this->get(rtrim($path, '/')) : null;
        if (null === $root) {
            throw new RuntimeException('Internal error');
        }

        return $root;
    }

    /**
     * Get a blueprint for a page type.
     *
     * @param  string $type
     * @return Blueprint
     */
    public function blueprints($type)
    {
        if ($this->blueprints === null) {
            $this->blueprints = new Blueprints(self::getTypes());
        }

        try {
            $blueprint = $this->blueprints->get($type);
        } catch (RuntimeException $e) {
            $blueprint = $this->blueprints->get('default');
        }

        if (empty($blueprint->initialized)) {
            $blueprint->initialized = true;
            $this->grav->fireEvent('onBlueprintCreated', new Event(['blueprint' => $blueprint, 'type' => $type]));
        }

        return $blueprint;
    }

    /**
     * Get all pages
     *
     * @param PageInterface|null $current
     * @return Collection
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
     * Get list of route/title of all pages. Title is in HTML.
     *
     * @param PageInterface|null $current
     * @param int $level
     * @param bool $rawRoutes
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
                throw new RuntimeException('Internal error');
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
                $option = htmlspecialchars($current->route());
            } else {
                $extra  = $showSlug ? '(' . $current->slug() . ') ' : '';
                $option = str_repeat('&mdash;-', $level). '&rtrif; ' . $extra . htmlspecialchars($current->title());
            }

            $list[$route] = $option;
        }

        if ($limitLevels === false || ($level+1 < $limitLevels)) {
            foreach ($current->children() as $next) {
                if ($showAll || $next->routable() || ($next->isModule() && $showModular)) {
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
        if (null === self::$types) {
            $grav = Grav::instance();

            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            // Prevent calls made before theme:// has been initialized (happens when upgrading old version of Admin plugin).
            if (!$locator->isStream('theme://')) {
                return new Types();
            }

            $scanBlueprintsAndTemplates = static function (Types $types) use ($grav) {
                // Scan blueprints
                $event = new Event();
                $event->types = $types;
                $grav->fireEvent('onGetPageBlueprints', $event);

                $types->init();

                // Try new location first.
                $lookup = 'theme://blueprints/pages/';
                if (!is_dir($lookup)) {
                    $lookup = 'theme://blueprints/';
                }
                $types->scanBlueprints($lookup);

                // Scan templates
                $event = new Event();
                $event->types = $types;
                $grav->fireEvent('onGetPageTemplates', $event);

                $types->scanTemplates('theme://templates/');
            };

            if ($grav['config']->get('system.cache.enabled')) {
                /** @var Cache $cache */
                $cache = $grav['cache'];

                // Use cached types if possible.
                $types_cache_id = md5('types');
                $types = $cache->fetch($types_cache_id);

                if (!$types instanceof Types) {
                    $types = new Types();
                    $scanBlueprintsAndTemplates($types);
                    $cache->save($types_cache_id, $types);
                }
            } else {
                $types = new Types();
                $scanBlueprintsAndTemplates($types);
            }

            // Register custom paths to the locator.
            $locator = $grav['locator'];
            foreach ($types as $type => $paths) {
                foreach ($paths as $k => $path) {
                    if (strpos($path, 'blueprints://') === 0) {
                        unset($paths[$k]);
                    }
                }
                if ($paths) {
                    $locator->addPath('blueprints', "pages/$type.yaml", $paths);
                }
            }

            self::$types = $types;
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
     * @param string|null $type
     * @return array
     */
    public static function pageTypes($type = null)
    {
        if (null === $type && isset(Grav::instance()['admin'])) {
            /** @var Admin $admin */
            $admin = Grav::instance()['admin'];

            /** @var PageInterface|null $page */
            $page = $admin->page();

            $type = $page && $page->isModule() ? 'modular' : 'standard';
        }

        switch ($type) {
            case 'standard':
                return static::types();
            case 'modular':
                return static::modularTypes();
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
            if ($page instanceof PageInterface && isset($page->header()->access)) {
                if (is_array($page->header()->access)) {
                    foreach ($page->header()->access as $index => $accessLevel) {
                        if (is_array($accessLevel)) {
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
     *
     * @return string|null
     */
    public static function resetHomeRoute()
    {
        self::$home_route = null;

        return self::getHomeRoute();
    }

    protected function initFlexPages(): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->grav['debugger'];
        $debugger->addMessage('Pages: Flex Directory');

        /** @var Flex $flex */
        $flex = $this->grav['flex'];
        $directory = $flex->getDirectory('pages');

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->grav['events'];

        // Stop /admin/pages from working, display error instead.
        $dispatcher->addListener(
            'onAdminPage',
            static function (Event $event) use ($directory) {
                $grav = Grav::instance();
                $admin = $grav['admin'];
                [$base,$location,] = $admin->getRouteDetails();
                if ($location !== 'pages' || isset($grav['flex_objects'])) {
                    return;
                }

                /** @var PageInterface $page */
                $page = $event['page'];
                $page->init(new SplFileInfo('plugin://admin/pages/admin/error.md'));
                $page->routable(true);
                $header = $page->header();
                $header->title = 'Please install missing plugin';
                $page->content("## Please install and enable **[Flex Objects]({$base}/plugins/flex-objects)** plugin. It is required to edit **Flex Pages**.");

                /** @var Header $header */
                $header = $page->header();
                $menu = $directory->getConfig('admin.menu.list');
                $header->access = $menu['authorize'] ?? ['admin.super'];
            },
            100000
        );

        $this->directory = $directory;
    }

    /**
     * Builds pages.
     *
     * @internal
     */
    protected function buildPages(): void
    {
        /** @var Debugger $debugger */
        $debugger = $this->grav['debugger'];
        $debugger->startTimer('build-pages', 'Init frontend routes');

        if ($this->directory) {
            $this->buildFlexPages($this->directory);
        } else {
            $this->buildRegularPages();
        }
        $debugger->stopTimer('build-pages');
    }

    protected function buildFlexPages(FlexDirectory $directory): void
    {
        /** @var Config $config */
        $config = $this->grav['config'];

        // TODO: right now we are just emulating normal pages, it is inefficient and bad... but works!
        /** @var PageCollection|PageIndex $collection */
        $collection = $directory->getIndex(null, 'storage_key');
        $cache = $directory->getCache('index');

        /** @var Language $language */
        $language = $this->grav['language'];

        $this->pages_cache_id = 'pages-' . md5($collection->getCacheChecksum() . $language->getActive() . $config->checksum());

        $cached = $cache->get($this->pages_cache_id);

        if ($cached && $this->getVersion() === $cached[0]) {
            [, $this->index, $this->routes, $this->children, $taxonomy_map, $this->sort] = $cached;

            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];
            $taxonomy->taxonomy($taxonomy_map);

            return;
        }

        /** @var Debugger $debugger */
        $debugger = $this->grav['debugger'];
        $debugger->addMessage('Page cache missed, rebuilding Flex Pages..');

        $root = $collection->getRoot();
        $root_path = $root->path();
        $this->routes = [];
        $this->instances = [$root_path => $root];
        $this->index = [$root_path => $root];
        $this->children = [];
        $this->sort = [];

        if ($this->fire_events) {
            $this->grav->fireEvent('onBuildPagesInitialized');
        }

        /** @var PageInterface $page */
        foreach ($collection as $page) {
            $path = $page->path();
            if (null === $path) {
                throw new RuntimeException('Internal error');
            }

            if ($page instanceof FlexTranslateInterface) {
                $page = $page->hasTranslation() ? $page->getTranslation() : null;
            }

            if (!$page instanceof FlexPageObject || $path === $root_path) {
                continue;
            }

            if ($this->fire_events) {
                if (method_exists($page, 'initialize')) {
                    $page->initialize();
                } else {
                    // TODO: Deprecated, only used in 1.7 betas.
                    $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
                }
            }

            $parent = dirname($path);

            $route = $page->rawRoute();

            // Skip duplicated empty folders (git revert does not remove those).
            // TODO: still not perfect, will only work if the page has been translated.
            if (isset($this->routes[$route])) {
                $oldPath = $this->routes[$route];
                if ($page->isPage()) {
                    unset($this->index[$oldPath], $this->children[dirname($oldPath)][$oldPath]);
                } else {
                    continue;
                }
            }

            $this->routes[$route] = $path;
            $this->instances[$path] = $page;
            $this->index[$path] = $page->getFlexKey();
            // FIXME: ... better...
            $this->children[$parent][$path] = ['slug' => $page->slug()];
            if (!isset($this->children[$path])) {
                $this->children[$path] = [];
            }
        }

        foreach ($this->children as $path => $list) {
            $page = $this->instances[$path] ?? null;
            if (null === $page) {
                continue;
            }
            // Call onFolderProcessed event.
            if ($this->fire_events) {
                $this->grav->fireEvent('onFolderProcessed', new Event(['page' => $page]));
            }
            // Sort the children.
            $this->children[$path] = $this->sort($page);
        }

        $this->routes = [];
        $this->buildRoutes();

        // cache if needed
        if (null !== $cache) {
            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];
            $taxonomy_map = $taxonomy->taxonomy();

            // save pages, routes, taxonomy, and sort to cache
            $cache->set($this->pages_cache_id, [$this->getVersion(), $this->index, $this->routes, $this->children, $taxonomy_map, $this->sort]);
        }
    }

    /**
     * @return Page
     */
    protected function buildRootPage()
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $path = $locator->findResource('page://');
        if (!is_string($path)) {
            throw new RuntimeException('Internal Error');
        }

        /** @var Config $config */
        $config = $grav['config'];

        $page = new Page();
        $page->path($path);
        $page->orderDir($config->get('system.pages.order.dir'));
        $page->orderBy($config->get('system.pages.order.by'));
        $page->modified(0);
        $page->routable(false);
        $page->template('default');
        $page->extension('.md');

        return $page;
    }

    protected function buildRegularPages(): void
    {
        /** @var Config $config */
        $config = $this->grav['config'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        /** @var Language $language */
        $language = $this->grav['language'];

        $pages_dir = $locator->findResource('page://');
        if (!is_string($pages_dir)) {
            throw new RuntimeException('Internal Error');
        }

        // Set active language
        $this->active_lang = $language->getActive();

        if ($config->get('system.cache.enabled')) {
            /** @var Language $language */
            $language = $this->grav['language'];

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

            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            $cached = $cache->fetch($this->pages_cache_id);
            if ($cached && $this->getVersion() === $cached[0]) {
                [, $this->index, $this->routes, $this->children, $taxonomy_map, $this->sort] = $cached;

                /** @var Taxonomy $taxonomy */
                $taxonomy = $this->grav['taxonomy'];
                $taxonomy->taxonomy($taxonomy_map);

                return;
            }

            $this->grav['debugger']->addMessage('Page cache missed, rebuilding pages..');
        } else {
            $this->grav['debugger']->addMessage('Page cache disabled, rebuilding pages..');
        }

        $this->resetPages($pages_dir);
    }

    /**
     * Accessible method to manually reset the pages cache
     *
     * @param string $pages_dir
     */
    public function resetPages($pages_dir): void
    {
        $this->sort = [];
        $this->recurse($pages_dir);
        $this->buildRoutes();

        // cache if needed
        if ($this->grav['config']->get('system.cache.enabled')) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            /** @var Taxonomy $taxonomy */
            $taxonomy = $this->grav['taxonomy'];

            // save pages, routes, taxonomy, and sort to cache
            $cache->save($this->pages_cache_id, [$this->getVersion(), $this->index, $this->routes, $this->children, $taxonomy->taxonomy(), $this->sort]);
        }
    }

    /**
     * Recursive function to load & build page relationships.
     *
     * @param string    $directory
     * @param PageInterface|null $parent
     * @return PageInterface
     * @throws RuntimeException
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
        if ($parent === null && $this->fire_events) {
            $this->grav->fireEvent('onBuildPagesInitialized');
        }

        $page->path($directory);
        if ($parent) {
            $page->parent($parent);
        }

        $page->orderDir($config->get('system.pages.order.dir'));
        $page->orderBy($config->get('system.pages.order.by'));

        // Add into instances
        if (!isset($this->index[$page->path()])) {
            $this->index[$page->path()] = $page;
            $this->instances[$page->path()] = $page;
            if ($parent && $page->path()) {
                $this->children[$parent->path()][$page->path()] = ['slug' => $page->slug()];
            }
        } elseif ($parent !== null) {
            throw new RuntimeException('Fatal error when creating page instances.');
        }

        // Build regular expression for all the allowed page extensions.
        $page_extensions = $language->getFallbackPageExtensions();
        $regex = '/^[^\.]*(' . implode('|', array_map(
            static function ($str) {
                return preg_quote($str, '/');
            },
            $page_extensions
        )) . ')$/';

        $folders = [];
        $page_found = null;
        $page_extension = '.md';
        $last_modified = 0;

        $iterator = new FilesystemIterator($directory);
        foreach ($iterator as $file) {
            $filename = $file->getFilename();

            // Ignore all hidden files if set.
            if ($this->ignore_hidden && $filename && strpos($filename, '.') === 0) {
                continue;
            }

            // Handle folders later.
            if ($file->isDir()) {
                // But ignore all folders in ignore list.
                if (!in_array($filename, $this->ignore_folders, true)) {
                    $folders[] = $file;
                }
                continue;
            }

            // Ignore all files in ignore list.
            if (in_array($filename, $this->ignore_files, true)) {
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

            if ($this->fire_events) {
                $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
            }
        }

        // Now handle all the folders under the page.
        /** @var FilesystemIterator $file */
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

            if (preg_match('/^(\d+\.)_/', $filename)) {
                $child->routable(false);
                $child->modularTwig(true);
            }

            $this->children[$page->path()][$child->path()] = ['slug' => $child->slug()];

            if ($this->fire_events) {
                $this->grav->fireEvent('onFolderProcessed', new Event(['page' => $page]));
            }
        }

        if (!$content_exists) {
            // Set routable to false if no page found
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
        $page->id($last_modified . md5($page->filePath() ?? ''));

        // Sort based on Defaults or Page Overridden sort order
        $this->children[$page->path()] = $this->sort($page);

        return $page;
    }

    /**
     * @internal
     */
    protected function buildRoutes(): void
    {
        /** @var Taxonomy $taxonomy */
        $taxonomy = $this->grav['taxonomy'];

        // Get the home route
        $home = self::resetHomeRoute();
        // Build routes and taxonomy map.
        /** @var PageInterface|string $page */
        foreach ($this->index as $path => $page) {
            if (is_string($page)) {
                $page = $this->get($path);
            }

            if (!$page || $page->root()) {
                continue;
            }

            // process taxonomy
            $taxonomy->addTaxonomy($page);

            $page_path = $page->path();
            if (null === $page_path) {
                throw new RuntimeException('Internal Error');
            }

            $route = $page->route();
            $raw_route = $page->rawRoute();

            // add regular route
            if ($route) {
                $this->routes[$route] = $page_path;
            }

            // add raw route
            if ($raw_route && $raw_route !== $route) {
                $this->routes[$raw_route] = $page_path;
            }

            // add canonical route
            $route_canonical = $page->routeCanonical();
            if ($route_canonical && $route !== $route_canonical) {
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

        // Alias and set default route to home page.
        $homeRoute = "/{$home}";
        if ($home && isset($this->routes[$homeRoute])) {
            $home = $this->get($this->routes[$homeRoute]);
            if ($home) {
                $this->routes['/'] = $this->routes[$homeRoute];
                $home->route('/');
            }
        }
    }

    /**
     * @param string $path
     * @param array  $pages
     * @param string $order_by
     * @param array|null  $manual
     * @param int|null    $sort_flags
     * @throws RuntimeException
     * @internal
     */
    protected function buildSort($path, array $pages, $order_by = 'default', $manual = null, $sort_flags = null): void
    {
        $list = [];
        $header_query = null;
        $header_default = null;

        // do this header query work only once
        if (strpos($order_by, 'header.') === 0) {
            $query = explode('|', str_replace('header.', '', $order_by), 2);
            $header_query = array_shift($query) ?? '';
            $header_default = array_shift($query);
        }

        foreach ($pages as $key => $info) {
            $child = $this->get($key);
            if (!$child) {
                throw new RuntimeException("Page does not exist: {$key}");
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
                    $list[$key] = Utils::basename($key);
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
                $locale = setlocale(LC_COLLATE, '0'); //`setlocale` with a '0' param returns the current locale set
                $col = Collator::create($locale);
                if ($col) {
                    $col->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
                    if (($sort_flags & SORT_NATURAL) === SORT_NATURAL) {
                        $list = preg_replace_callback('~([0-9]+)\.~', static function ($number) {
                            return sprintf('%032d.', $number[0]);
                        }, $list);
                        if (!is_array($list)) {
                            throw new RuntimeException('Internal Error');
                        }

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
                $order = array_search($info['slug'], $manual, true);
                if ($order === false) {
                    $order = $i++;
                }
                $new_list[$key] = (int)$order;
            }

            $list = $new_list;

            // Apply manual ordering to the list.
            asort($list, SORT_NUMERIC);
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
     * @return string
     */
    protected function getVersion()
    {
        return $this->directory ? 'flex' : 'regular';
    }

    /**
     * Get the Pages cache ID
     *
     * this is particularly useful to know if pages have changed and you want
     * to sync another cache with pages cache - works best in `onPagesInitialized()`
     *
     * @return string
     */
    public function getPagesCacheId()
    {
        return $this->pages_cache_id;
    }
}
