<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
use Grav\Common\File\CompiledMarkdownFile;
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
use Grav\Events\TypesEvent;
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
use function json_encode;
use function md5;

/**
 * Class Pages
 * @package Grav\Common\Page
 */
class Pages
{
    /** Seconds a request waits for another request to finish rebuilding the pages before it rebuilds them itself. */
    protected const REBUILD_LOCK_TIMEOUT = 15;

    /** File in cache/compiled/pages holding the stamp that markChanged() bumps. */
    protected const CHANGE_STAMP_FILE = 'change-stamp.txt';

    /** Page count from which system.pages.lazy_index: auto uses the lazy page index. */
    protected const LAZY_INDEX_AUTO_PAGES = 1000;

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
    protected $simple_pages_hash;
    /** @var string */
    protected $pages_cache_id;
    /** @var bool */
    protected $initialized = false;
    /** @var string */
    protected $active_lang;
    /** @var string|null */
    protected $page_extension_regex;
    /** @var bool */
    protected $fire_events = false;
    /** @var PageIndexStore|null Per-page store backing a lazily hydrated regular pages index. */
    protected $index_store;
    /** @var bool Guard against recursive rebuilds when a lazily indexed page fails to load. */
    protected $index_store_rebuilding = false;
    /** @var bool Routes live in the index store; $this->routes only overlays runtime additions and memoized reads. */
    protected $routes_lazy = false;
    /** @var bool Children lists live in the index store; $this->children only overlays runtime additions and memoized reads. */
    protected $children_lazy = false;
    /** @var bool Sort orders live in the index store; $this->sort only overlays runtime-built orders and memoized reads. */
    protected $sort_lazy = false;
    /** @var array<string,int|false>|null Folders and files the running rebuild has seen, with their modification times. */
    protected $scan_paths;
    /** @var array<string,array>|null Folder listings the previous rebuild recorded: folder => [mtime, time recorded, entries]. */
    protected $scan_folders_previous;
    /** @var array<string,array>|null Folder listings the running rebuild records for the next one. */
    protected $scan_folders;
    /** @var int Time the running rebuild started. */
    protected $scan_started = 0;
    /** @var bool True while this process rebuilds the pages, so a nested rebuild never waits for its own lock. */
    private static $rebuilding = false;
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
        $key = ($lang ?: $this->active_lang) ?: 'default';

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

        // Start by checking that referrer came from our site. The root carries no trailing slash, so the prefix has
        // to be anchored on a path separator, or a host that merely starts with the same characters passes as ours.
        $root = (string) $this->grav['base_url_absolute'];
        if (!is_string($referrer) || !($referrer === $root || str_starts_with($referrer, $root . '/'))) {
            return null;
        }

        /** @var Language $language */
        $language = $this->grav['language'];

        // Get all language codes and append no language.
        if (null === $langCode) {
            $languages = $language->enabled() ? $language->getLanguages() : [];
            $languages[] = '';
        } else {
            $languages = [$langCode];
        }

        $path_base = rtrim($this->base(), '/');
        $path_route = rtrim($route, '/');

        // Try to figure out the language code. $referrer is absolute, so the candidates need the site root on them
        // as well, or nothing ever matches and the method always returns null.
        foreach ($languages as $code) {
            $path_lang = $code ? "/{$code}" : '';

            $base = $root . $path_base . $path_lang . $path_route;
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
        // Full listings hydrate every page anyway, so pull all payloads from the
        // index store in one query instead of a point-read per page.
        $this->hydrateIndexedPages();

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
     * Bulk-hydrate all lazily indexed pages from the per-page index store.
     *
     * @return void
     */
    protected function hydrateIndexedPages(): void
    {
        if (!$this->index_store) {
            return;
        }

        $payloads = null;
        foreach ($this->index as $path => $instance) {
            if ($instance === true && !array_key_exists($path, $this->instances)) {
                $payloads ??= $this->index_store->readAll();

                $page = isset($payloads[$path]) ? @unserialize($payloads[$path]) : null;
                if ($page instanceof PageInterface) {
                    $this->instances[$path] = $page;
                }
                // Missing rows fall through to get(), which rebuilds the index.
            }
        }
    }

    /**
     * Load several lazily indexed pages with one query per batch instead of a
     * query per page. Collections call this before they walk their pages.
     *
     * Does nothing unless the pages come from the lazy page index, and skips
     * pages that are already loaded. Pages whose rows can't be read are left
     * for get(), which rebuilds the index as it always has.
     *
     * @param iterable<string> $paths
     * @return void
     */
    public function prefetch(iterable $paths): void
    {
        if (!$this->index_store) {
            return;
        }

        $missing = [];
        foreach ($paths as $path) {
            $path = (string)$path;
            if (($this->index[$path] ?? null) === true && !array_key_exists($path, $this->instances)) {
                $missing[] = $path;
            }
        }
        if (!$missing) {
            return;
        }

        foreach ($this->index_store->readMany($missing) as $path => $payload) {
            $page = @unserialize($payload);
            if ($page instanceof PageInterface) {
                $this->instances[$path] = $page;
            }
        }
    }

    /**
     * True when the page at this path is in the lazy page index and has not
     * been loaded yet, so reading it would cost a query.
     *
     * Always false when the pages come from the classic cache.
     *
     * @param string $path
     * @return bool
     */
    public function isLazilyIndexed($path): bool
    {
        $path = (string)$path;

        return ($this->index[$path] ?? null) === true && !array_key_exists($path, $this->instances);
    }

    /**
     * Load the children lists of several folders with one query per batch,
     * when children lists come from the lazy page index.
     *
     * @param string[] $paths
     * @return void
     */
    protected function prefetchChildren(array $paths): void
    {
        if (!$this->children_lazy || !$this->index_store) {
            return;
        }

        $missing = [];
        foreach ($paths as $path) {
            if (!array_key_exists($path, $this->children)) {
                $missing[] = $path;
            }
        }
        if (!$missing) {
            return;
        }

        $rows = $this->index_store->readChildrenMany($missing);
        foreach ($missing as $path) {
            // Same as childrenOf(): a folder without a stored list has no children.
            $this->children[$path] = $rows[$path] ?? [];
        }
    }

    /**
     * Report how the pages index is being served this request, for the debugger.
     *
     * 'mode' is 'lazy' (per-page index store), 'blob' (classic single cache
     * entry) or 'flex'. For lazy/blob, 'total' is the number of pages in the
     * index and 'hydrated' is how many Page objects this request has actually
     * built - the ratio shows whether the lazy index is saving work.
     *
     * @return array
     */
    public function getIndexStats(): array
    {
        if ($this->directory) {
            return ['mode' => 'flex'];
        }

        $stats = [
            'mode' => $this->index_store ? 'lazy' : 'blob',
            'total' => count($this->index),
            'hydrated' => count($this->instances),
        ];
        if ($this->index_store) {
            $stats['engine'] = $this->index_store->getEngine();
        }

        return $stats;
    }

    /**
     * Returns a list of all routes.
     *
     * @return array
     */
    public function routes()
    {
        if ($this->routes_lazy && $this->index_store) {
            // Load the full stored map once; runtime-added routes win over stored ones.
            $this->routes = array_replace($this->index_store->readAllRoutes(), $this->routes);
            $this->routes_lazy = false;
        }

        return $this->routes;
    }

    /**
     * Resolve a route to a page path, using the index store for point lookups
     * when the route map is lazy.
     *
     * @param string $route
     * @return string|null
     */
    protected function routeToPath(string $route): ?string
    {
        $path = $this->routes[$route] ?? null;
        if (null === $path && $this->routes_lazy && $this->index_store) {
            $path = $this->index_store->readRoute($route);
            if (null !== $path) {
                $this->routes[$route] = $path;
            }
        }

        return $path;
    }

    /**
     * Get the children list of a parent path, reading through to the index
     * store when the children map is lazy.
     *
     * @param string $path
     * @return array
     */
    protected function childrenOf(string $path): array
    {
        if ($this->children_lazy && $this->index_store && !array_key_exists($path, $this->children)) {
            $this->children[$path] = $this->index_store->readChildren($path) ?? [];
        }

        return $this->children[$path] ?? [];
    }

    /**
     * Get the precomputed sort orders of a parent path, reading through to the
     * index store when the sort map is lazy.
     *
     * @param string $path
     * @return array
     */
    protected function sortOf(string $path): array
    {
        if ($this->sort_lazy && $this->index_store && !array_key_exists($path, $this->sort)) {
            $this->sort[$path] = $this->index_store->readSort($path) ?? [];
        }

        return $this->sort[$path] ?? [];
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
            $parentPath = $parent->path() ?? '';
            // Materialize the stored children first so the runtime addition
            // extends the list instead of shadowing it.
            $this->children[$parentPath] = $this->childrenOf($parentPath);
            $this->children[$parentPath][$path] = ['slug' => $page->slug()];
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
                $param = $uri->param(rawurlencode((string) $taxonomy));
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
            if (str_starts_with((string) $type, 'non-')) {
                $type = substr((string) $type, 4);
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
                $sort_flags = array_reduce($sort_flags, static fn($a, $b) => $a | $b, 0); //merge constant values using bit or
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
    protected function evaluate($value, ?PageInterface $self = null)
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
                // Keep the children index flags on each item, so the module and published
                // filters that follow don't load every descendant to ask it.
                $collection = (new Collection($this->allItems($page, true), [], $this))->remove($page->path())->pages();
                break;
            default:
                // Unknown type; return empty collection.
                $collection = new Collection();
                break;
        }

        if (!$collection instanceof Collection) {
            $collection = new Collection($collection->toArray());
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

        $children = $this->childrenOf($path);

        if (!$children) {
            return $children;
        }

        if (!isset($this->sortOf($path)[$order_by])) {
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
        if (!isset($this->sortOf($lookup)[$orderBy])) {
            $this->buildSort($lookup, $items, $orderBy, $orderManual, $sort_flags);
        }

        $sort = $this->sort[$lookup][$orderBy];

        if ($orderDir !== 'asc') {
            $sort = array_reverse($sort);
        }

        return $sort;
    }

    /**
     * Check whether a page path has already been hydrated into memory, without
     * triggering hydration.
     *
     * Collection flag filters use this to prefer a page's live flags (which a
     * plugin may have changed at runtime, e.g. the Login plugin's dynamic page
     * visibility) over the flags frozen in the children index, while still
     * avoiding a load for pages that are only lazily indexed. See getgrav/grav#4201.
     *
     * @param string $path
     * @return bool
     */
    public function isInstantiated($path): bool
    {
        return array_key_exists((string)$path, $this->instances);
    }

    /**
     * Get a page instance.
     *
     * @param  string $path The filesystem full path of the page
     * @return PageInterface|null
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
        if ($instance === true) {
            // Lazily hydrate a regular page from the per-page index store.
            $instance = $this->loadIndexedPage($path);
        } elseif (is_string($instance)) {
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
     * Hydrate a single regular page from the per-page index store.
     *
     * A missing or unreadable row means the store and the cached index have
     * drifted apart (for example the file was deleted mid-request), so the
     * whole index is rebuilt once from the filesystem as a fallback.
     *
     * @param string $path
     * @return PageInterface|null
     */
    protected function loadIndexedPage(string $path): ?PageInterface
    {
        $payload = $this->index_store ? $this->index_store->read($path) : null;
        $instance = is_string($payload) ? @unserialize($payload) : null;
        if ($instance instanceof PageInterface) {
            return $instance;
        }

        if (!$this->index_store_rebuilding) {
            $this->index_store_rebuilding = true;

            /** @var Debugger $debugger */
            $debugger = $this->grav['debugger'];
            $debugger->addMessage(sprintf('Lazily indexed page %s is missing or broken, rebuilding pages..', $path), 'debug');

            $this->resetPages($this->getPagesPaths());
            $this->index_store_rebuilding = false;

            $instance = $this->index[$path] ?? null;
            if ($instance instanceof PageInterface) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Get children of the path.
     *
     * @param string $path
     * @return Collection
     */
    public function children($path)
    {
        $children = $this->childrenOf((string)$path);
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
        $route = str_replace($this->base, '', $route);

        // Fetch page if there's a defined route to it.
        $path = $this->routeToPath($route);
        $page = null !== $path ? $this->get($path) : null;

        // Try without trailing slash
        if (null === $page && Utils::endsWith($route, '/')) {
            $path = $this->routeToPath(rtrim($route, '/'));
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
                $pattern = '#^' . str_replace('/', '\/', ltrim((string) $pattern, '^')) . '#';
                try {
                    $found = preg_replace($pattern, (string) $replace, $route);
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
    /**
     * Whether the current request asks for a page in a specific output format
     * (`.md`, `.rss`, `.json`…) by its URL extension.
     *
     * @return bool
     */
    protected function isFormatRequest(): bool
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $extension = $uri->extension();

        return is_string($extension) && $uri->isValidExtension($extension);
    }

    public function dispatch($route, $all = false, $redirect = true)
    {
        $page = $this->find($route, true);

        // `/index.<ext>` is the home page in that output format: the URL
        // Page::url() builds for it, since `/.<ext>` is a hidden file to every
        // web server. A root page really called `index` still wins.
        if (($page === null || !$page->routable()) && $route === '/index' && $this->isFormatRequest()) {
            $page = $this->find('/', true);
        }

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
                $pattern = ltrim((string) $pattern, '^');
                $pattern = '#^' . str_replace('/', '\/', $pattern) . '#';
                try {
                    /** @var string $found */
                    $found = preg_replace($pattern, (string) $replace, $source_url);
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
        } catch (RuntimeException) {
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
    public function all(?PageInterface $current = null)
    {
        return new Collection($this->allItems($current ?: $this->root(), false), [], $this);
    }

    /**
     * The items of all(): the page and everything below it, depth first.
     *
     * With $withInfo each item keeps the flags and sort keys the children index
     * holds for it (as children() collections do), so filters and sorts on the
     * result don't have to load every page. all() itself keeps its items to the
     * slug alone, as it always has.
     *
     * @param PageInterface $current
     * @param bool $withInfo
     * @return array<string,array>
     */
    protected function allItems(PageInterface $current, bool $withInfo): array
    {
        $items = [];
        if (!$current->root()) {
            $items[$current->path()] = ['slug' => $current->slug()];
        }

        if (!$this->directory && $current instanceof Page) {
            // With a lazy index, load the children lists a level at a time in batches
            // instead of one query per folder during the walk below.
            if ($this->children_lazy && $this->index_store) {
                $level = [(string)$current->path()];
                while ($level) {
                    $this->prefetchChildren($level);
                    $next = [];
                    foreach ($level as $path) {
                        foreach ($this->children[$path] ?? [] as $childPath => $info) {
                            $next[] = (string)$childPath;
                        }
                    }
                    $level = $next;
                }
            }

            // Regular pages: walk the children index by path, depth first in children order,
            // which is the order the recursive walk produced. Pages are not loaded just to find
            // their children: the slug comes from the page when it is already in memory and
            // from the children index otherwise.
            $stack = [];
            foreach (array_reverse($this->childrenOf((string)$current->path()), true) as $path => $info) {
                $stack[] = [(string)$path, $info];
            }

            while ($stack) {
                [$path, $info] = array_pop($stack);

                $page = $this->instances[$path] ?? $this->index[$path] ?? null;
                $slug = $page instanceof PageInterface ? $page->slug() : ($info['slug'] ?? null);
                if ($slug === null) {
                    $page = $this->get($path);
                    $slug = $page ? $page->slug() : null;
                }
                $items[$path] = $withInfo && is_array($info) ? ['slug' => $slug] + $info : ['slug' => $slug];

                foreach (array_reverse($this->childrenOf($path), true) as $childPath => $childInfo) {
                    $stack[] = [(string)$childPath, $childInfo];
                }
            }
        } else {
            // Flex pages and other page types may not take their children from the children
            // index, so ask each page for them, depth first like the regular walk.
            $iterate = static function ($children): \Generator {
                foreach ($children as $child) {
                    yield $child;
                }
            };

            $stack = [$iterate($current->children())];
            while ($stack) {
                $iterator = end($stack);
                if (!$iterator->valid()) {
                    array_pop($stack);
                    continue;
                }

                $next = $iterator->current();
                $iterator->next();
                if ($next instanceof PageInterface) {
                    $items[$next->path()] = ['slug' => $next->slug()];
                    $stack[] = $iterate($next->children());
                }
            }
        }

        return $items;
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

        /** @var Cache $cache */
        $cache = $grav['cache'];
        $cache_id = 'parents-' . ($rawRoutes ? 'raw-' : '') . $grav['language']->getActive();
        $parents = $cache->fetch($cache_id);

        if ($parents === false) {
            /** @var Pages $pages */
            $pages = $grav['pages'];
            $parents = $pages->getList(null, 0, $rawRoutes);
            $cache->save($cache_id, $parents);
        }

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
    public function getList(?PageInterface $current = null, $level = 0, $rawRoutes = false, $showAll = true, $showFullpath = false, $showSlug = false, $showModular = false, $limitLevels = false)
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
                $option = htmlspecialchars((string) $current->route());
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
                $event = new TypesEvent();
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
                $event = new TypesEvent();
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
                    if (str_starts_with((string) $path, 'blueprints://')) {
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
        return match ($type) {
            'standard' => static::types(),
            'modular' => static::modularTypes(),
            default => [],
        };
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
                    } catch (ErrorException) {
                        $home = $home_aliases[$default];
                    }
                }
            }

            self::$home_route = trim((string) $home, '/');
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

        /** @var Language $language */
        $language = $this->grav['language'];

        $pages_dirs = $this->getPagesPaths();

        // Set active language
        $this->active_lang = $language->getActive();

        if ($config->get('system.cache.enabled')) {
            $interval = (int)$config->get('system.cache.check.interval', 0);
            $method = (string)$this->check_method;
            $this->setPagesCacheId($pages_dirs, $this->resolvePagesHash($pages_dirs, $interval, $method));

            /** @var Cache $cache */
            $cache = $this->grav['cache'];
            if ($this->loadCachedPages($cache, $pages_dirs)) {
                return;
            }

            // Only one request rebuilds at a time. The others wait for it and then read the
            // cache it wrote; if it takes too long, they rebuild the pages themselves.
            $waited = false;
            $lock = self::$rebuilding ? null : $this->acquireLock('rebuild-' . md5(json_encode($pages_dirs) . $this->active_lang), static::REBUILD_LOCK_TIMEOUT, $waited);
            $rebuilding = self::$rebuilding;
            self::$rebuilding = true;
            try {
                if ($waited) {
                    $this->setPagesCacheId($pages_dirs, $this->resolvePagesHash($pages_dirs, $interval, $method));
                    if ($this->loadCachedPages($cache, $pages_dirs)) {
                        return;
                    }
                }

                $this->grav['debugger']->addMessage('Page cache missed, rebuilding pages..');
                $this->resetPages($pages_dirs);
            } finally {
                self::$rebuilding = $rebuilding;
                $this->releaseLock($lock);
            }

            return;
        }

        $this->grav['debugger']->addMessage('Page cache disabled, rebuilding pages..');
        $this->resetPages($pages_dirs);
    }

    /**
     * Load the pages index from the cache entry for the current pages cache id.
     *
     * @param Cache $cache
     * @param array $pages_dirs
     * @return bool True if the cached index was used.
     */
    protected function loadCachedPages(Cache $cache, array $pages_dirs): bool
    {
        $cached = $cache->fetch($this->pages_cache_id);
        if (!$cached || $this->getVersion() !== $cached[0]) {
            return false;
        }

        // A lazy index stores true-markers instead of Page objects; the pages
        // themselves live in the per-page index store and hydrate on access.
        $lazy = !empty($cached[6]);
        $store = $lazy ? $this->openIndexStore($pages_dirs) : null;
        if ($lazy && !($store && $store->isValid($this->pages_cache_id))) {
            return false;
        }

        $this->index_store = $store;
        [, $this->index, $this->routes, $this->children, $taxonomy_map, $this->sort] = $cached;

        /** @var Taxonomy $taxonomy */
        $taxonomy = $this->grav['taxonomy'];
        if ($lazy) {
            /** @var Language $language */
            $language = $this->grav['language'];

            // Routes, children lists, sort orders and the taxonomy map
            // live in the index store and load on first use.
            $this->routes_lazy = true;
            $this->children_lazy = true;
            $this->sort_lazy = true;
            $taxonomy->setLoader(
                fn() => $this->index_store ? $this->index_store->readTaxonomy() : [],
                fn(string $type, string $value) => $this->index_store ? $this->index_store->readTaxonomyValue($type, $value) : [],
                $language->getLanguage()
            );
        } else {
            $taxonomy->taxonomy($taxonomy_map);
        }

        return true;
    }

    /**
     * Set the pages cache id from the pages hash, the change stamp, the configuration and the language.
     *
     * @param array $pages_dirs
     * @param string|int $hash
     * @return void
     */
    protected function setPagesCacheId(array $pages_dirs, $hash): void
    {
        /** @var Language $language */
        $language = $this->grav['language'];

        $this->simple_pages_hash = json_encode($pages_dirs) . $hash . $this->getChangeStamp() . $this->grav['config']->checksum();
        $this->pages_cache_id = md5($this->simple_pages_hash . $language->getActive());
    }

    protected function getPagesPaths(): array
    {
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $paths = [];

        $dirs = (array) $grav['config']->get('system.pages.dirs', ['page://']);
        foreach ($dirs as $dir) {
            $path = $locator->findResource($dir);
            if (file_exists($path) && !in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Accessible method to manually reset the pages cache
     *
     * @param array $pages_dirs
     */
    public function resetPages(array $pages_dirs): void
    {
        // A full rebuild produces complete in-memory maps, so any lazy state from
        // a previously loaded cache no longer applies.
        $this->routes_lazy = false;
        $this->children_lazy = false;
        $this->sort_lazy = false;
        $this->sort = [];

        /** @var Taxonomy $taxonomy */
        $taxonomy = $this->grav['taxonomy'];
        $taxonomy->setLoader(null);

        /** @var Config $config */
        $config = $this->grav['config'];
        $cache_enabled = (bool)$config->get('system.cache.enabled');
        $method = (string)$this->check_method;

        // Record what the scan sees, so the change check can stat these paths instead of walking the tree.
        $check = $cache_enabled && $this->pages_cache_id && $method !== 'none' && $method !== 'off';
        $this->scan_paths = $check ? [] : null;

        // Page files skip the per-file compiled cache during the scan, and the scan reuses what the last one
        // read where the files and folders are unchanged: the parsed header of each page file whose time and
        // size are the same, and the listing of each folder whose time is the same. Every page is still built
        // and every page event still fires; only the file reads and folder listings are skipped.
        $state_file = $this->getScanFile('scan-' . md5(json_encode($pages_dirs) . $this->active_lang . (CompiledMarkdownFile::$nativeYaml ? '-native' : '')));
        $state = $state_file ? ($this->readScanFile($state_file) ?? []) : [];
        $scanning = CompiledMarkdownFile::beginScan((array)($state['headers'] ?? []), (array)($state['files'] ?? []));
        $reuse = $scanning && $state_file;
        if ($reuse) {
            $this->scan_folders_previous = (array)($state['folders'] ?? []);
            $this->scan_folders = [];
            $this->scan_started = time();
        }
        try {
            foreach ($pages_dirs as $dir) {
                $this->recurse($dir);
            }
        } finally {
            if ($scanning) {
                $headers = CompiledMarkdownFile::endScan($changed, $files);
                if ($reuse && ($changed || $files !== ($state['files'] ?? null) || $this->scan_folders !== $this->scan_folders_previous)) {
                    $this->writeScanFile($state_file, ['headers' => $headers, 'files' => $files, 'folders' => $this->scan_folders]);
                }
            }
            if ($reuse) {
                $this->scan_folders = $this->scan_folders_previous = null;
            }
        }

        $this->buildRoutes();
        $this->enrichChildrenIndex();

        if ($this->scan_paths !== null) {
            // Cache the pages under the hash of what the scan saw rather than the hash the request
            // started with, so a cached index always matches the tree it was built from. Without
            // a saved scan state the next check could not reproduce that hash, so keep the old one.
            $hash = $this->hashScanPaths($this->scan_paths);
            $state_file = $this->getScanFile('check-' . md5(json_encode($pages_dirs) . $method));
            if ($state_file && $this->writeScanFile($state_file, ['hash' => $hash, 'paths' => $this->scan_paths])) {
                $interval = (int)$config->get('system.cache.check.interval', 0);
                if ($interval > 0) {
                    $this->grav['cache']->save($this->getPagesHashKey($pages_dirs, $method), $hash, $interval);
                }

                $this->setPagesCacheId($pages_dirs, $hash);
            }
            $this->scan_paths = null;
        }

        // cache if needed
        if ($cache_enabled) {
            /** @var Cache $cache */
            $cache = $this->grav['cache'];

            // Leave the raw frontmatter text out of the cache. It is a second copy of the header
            // and Page::frontmatter() reads it from the file when it is asked for. Clearing it
            // here costs far less than a __sleep() on every page would.
            foreach ($this->index as $page) {
                if ($page instanceof Page) {
                    $page->freeFrontmatter();
                }
            }

            // Store each page as its own row - along with the route, children, sort
            // and taxonomy maps - so warm requests hydrate only what they touch,
            // instead of unserializing data for every page on the site. The full
            // in-memory index built above keeps its objects for this request.
            $index = $this->index;
            $lazy = false;
            if ($this->pages_cache_id) {
                $store = $this->lazyIndexEnabled(count($this->index)) ? ($this->index_store ?? $this->openIndexStore($pages_dirs)) : null;
                if ($store && $store->rebuild($this->pages_cache_id, [
                    'pages' => $this->serializeIndex(),
                    'routes' => $this->routes,
                    'children' => $this->children,
                    'sorts' => $this->sort,
                    'taxonomy' => $taxonomy->taxonomy(),
                ])) {
                    $this->index_store = $store;
                    $lazy = true;
                    $index = array_fill_keys(array_keys($this->index), true);
                }
            }

            if (!$lazy) {
                // The pages this request built are all in memory; don't keep a store from an earlier load.
                $this->index_store = null;
            }

            // save pages, routes, taxonomy, and sort to cache
            if ($lazy) {
                $cache->save($this->pages_cache_id, [$this->getVersion(), $index, [], [], [], [], true]);
            } else {
                $cache->save($this->pages_cache_id, [$this->getVersion(), $index, $this->routes, $this->children, $taxonomy->taxonomy(), $this->sort, false]);
            }
        }
    }

    /**
     * Record each child's menu-relevant flags in the children index.
     *
     * Navigation filters a folder's children down to the visible ones, and
     * collections filter by routable/published/module. Those flags are set at
     * page init (folder prefix, header, publish state) and are frozen in the
     * cache just like the rest of the page, so storing them alongside each
     * child lets the Collection filters prune without hydrating every page
     * first - the whole point when the index is lazy. Runs once at build time,
     * when every page is already in memory.
     *
     * @return void
     */
    protected function enrichChildrenIndex(): void
    {
        foreach ($this->children as $parentPath => $list) {
            foreach ($list as $childPath => $info) {
                $child = $this->index[$childPath] ?? null;
                if ($child instanceof PageInterface) {
                    $this->children[$parentPath][$childPath] = [
                        'slug' => $info['slug'] ?? $child->slug(),
                        'visible' => $child->visible(),
                        'routable' => $child->routable(),
                        'published' => $child->published(),
                        'module' => $child->isModule(),
                        // Common sort keys, frozen here so ordered collections can
                        // be sorted straight from the index without loading pages.
                        'title' => $child->title(),
                        'date' => $child->date(),
                        'modified' => $child->modified(),
                        'publish_date' => $child->publishDate(),
                        'folder' => $child->folder(),
                    ];
                }
            }
        }
    }

    /**
     * Serialize the in-memory page index for the per-page index store.
     *
     * @return \Generator<string,string>
     */
    protected function serializeIndex(): \Generator
    {
        foreach ($this->index as $path => $page) {
            if ($page instanceof PageInterface) {
                yield $path => serialize($page);
            }
        }
    }

    /**
     * Whether the regular pages use the lazy page index (system.pages.lazy_index).
     *
     * true turns it on, false keeps the classic single-blob pages cache, and
     * 'auto' (the default) turns it on for sites with at least LAZY_INDEX_AUTO_PAGES
     * pages. $pageCount is the size of the index being built; null means an
     * existing lazy cache is being opened, which 'auto' accepts as it is.
     *
     * @param int|null $pageCount
     * @return bool
     */
    protected function lazyIndexEnabled(?int $pageCount = null): bool
    {
        if ($this->directory) {
            return false;
        }

        $setting = $this->grav['config']->get('system.pages.lazy_index', 'auto');
        if ($setting === 'auto') {
            return $pageCount === null || $pageCount >= static::LAZY_INDEX_AUTO_PAGES;
        }

        return $setting && $setting !== 'false';
    }

    /**
     * Open the per-page index store backing the lazy regular pages index.
     * Returns null when the lazy index is off (see lazyIndexEnabled()), when
     * Flex pages are active, or when no supported database engine (pdo_sqlite
     * or YetiSQL) is available.
     *
     * @param array $pagesDirs
     * @param int|null $pageCount
     * @return PageIndexStore|null
     */
    protected function openIndexStore(array $pagesDirs, ?int $pageCount = null): ?PageIndexStore
    {
        if (!$this->lazyIndexEnabled($pageCount)) {
            return null;
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $dir = $locator->findResource('cache://compiled/pages', true, true);
        if (!is_string($dir)) {
            return null;
        }

        // Stable name per page dirs + language; content changes are detected via
        // the cache id stored inside the file, so the file gets rewritten in place.
        $name = 'index-' . md5(json_encode($pagesDirs) . (string)$this->active_lang);

        return PageIndexStore::open($dir, $name);
    }

    /**
     * List a folder for the pages scan: entry name => 'd' for folders, 'f' for everything else.
     *
     * Broken symlinks are left out. When the last scan recorded this folder with the same
     * modification time, and the folder was not modified in the second that scan listed it,
     * its listing is reused instead of reading the folder again: adding, removing or renaming
     * an entry always changes the folder's time.
     *
     * @param string $directory
     * @param int|false $time Folder modification time, taken before listing.
     * @param bool $reused Set to true when the listing comes from the last scan.
     * @return array<string,string>
     */
    protected function listFolder(string $directory, $time, bool &$reused = false): array
    {
        $reused = false;
        $known = $this->scan_folders_previous[$directory] ?? null;
        if ($time !== false && is_array($known) && ($known[0] ?? null) === $time && $time < ($known[1] ?? 0) && is_array($known[2] ?? null)) {
            $reused = true;
            $entries = $known[2];
        } else {
            $entries = [];
            foreach (new FilesystemIterator($directory) as $file) {
                // Skip broken symlinks.
                if ($file->isLink() && $file->getRealPath() === false) {
                    continue;
                }
                $entries[$file->getFilename()] = $file->isDir() ? 'd' : 'f';
            }
            $known = [$time, $this->scan_started, $entries];
        }

        if ($this->scan_folders !== null && $time !== false) {
            $this->scan_folders[$directory] = $known;
        }

        return $entries;
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
    protected function recurse(string $directory, ?PageInterface $parent = null)
    {
        $directory = rtrim($directory, DS);
        $page = new Page;

        // Take the folder time before listing it, so an entry added during the scan still counts as a change.
        $scan = $this->scan_paths !== null ? (string)$this->check_method : null;
        $folder_time = @filemtime($directory);
        if ($scan !== null) {
            $this->scan_paths[$directory] = $folder_time;
        }

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

        $page_extensions = array_flip($language->getFallbackPageExtensions());
        
        // $regex = $this->page_extension_regex;

        $folders = [];
        $page_found = null;
        $page_extension = '.md';
        $last_modified = 0;

        $ignore_files = array_flip($this->ignore_files);
        $ignore_folders = array_flip($this->ignore_folders);

        $reused = false;
        foreach ($this->listFolder($directory, $folder_time, $reused) as $filename => $type) {
            $filename = (string)$filename;

            // Ignore all hidden files if set.
            if ($this->ignore_hidden && $filename && str_starts_with($filename, '.')) {
                continue;
            }

            $pathname = $directory . DS . $filename;

            // Handle folders later.
            if ($type === 'd') {
                // But ignore all folders in ignore list.
                if (!isset($ignore_folders[$filename])) {
                    $folders[] = $filename;
                }
                continue;
            }

            // Ignore all files in ignore list.
            if (isset($ignore_files[$filename])) {
                continue;
            }

            // Update last modified date to match the last updated file in the folder.
            $modified = @filemtime($pathname);
            if ($modified === false) {
                // A listing reused from the last scan can name a symlink that has broken since.
                continue;
            }
            if ($modified > $last_modified) {
                $last_modified = $modified;
            }

            // The folder method checks folders only; hash checks every file; file checks pages and YAML.
            if ($scan !== null && $scan !== 'folder') {
                $lower = strtolower($filename);
                if ($scan === 'hash' || str_ends_with($lower, '.md') || str_ends_with($lower, '.yaml')) {
                    $this->scan_paths[$pathname] = $modified;
                }
            }

            // Page is the one that matches to $page_extensions list with the lowest index number.
            // Optimized version avoiding preg_match
            $pos = strpos($filename, '.');
            if ($pos !== false && $pos > 0) {
                $ext = substr($filename, $pos);
                if (isset($page_extensions[$ext])) {
                    if ($page_found === null || $page_extensions[$ext] < $page_extensions[$page_extension]) {
                        $page_found = $pathname;
                        $page_extension = $ext;
                    }
                }
            }
        }

        $content_exists = false;
        if ($parent && $page_found) {
            $page->init(new SplFileInfo($page_found), $page_extension);

            $content_exists = true;

            if ($this->fire_events) {
                $this->grav->fireEvent('onPageProcessed', new Event(['page' => $page]));
            }
        }

        // Now handle all the folders under the page.
        foreach ($folders as $filename) {
            // if folder contains separator, continue
            if (Utils::contains($filename, $config->get('system.param_sep', ':'))) {
                continue;
            }

            $path = $directory . DS . $filename;

            // A listing reused from the last scan can name a folder symlink that has broken since.
            if ($reused && !is_dir($path)) {
                continue;
            }

            if (!$page->path()) {
                $page->path($directory);
            }

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
        /** @var PageInterface|string|bool $page */
        foreach ($this->index as $path => $page) {
            if (!$page instanceof PageInterface) {
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
                if (isset($this->routes[$route]) && $this->routes[$route] !== $page_path) {
                    $this->grav['debugger']->addMessage("Route '{$route}' already exists: {$this->routes[$route]}, overwriting with {$page_path}");
                }
                $this->routes[$route] = $page_path;
            }

            // add raw route
            if ($raw_route) {
                if (isset($this->routes[$raw_route]) && $this->routes[$route] !== $page_path) {
                    $this->grav['debugger']->addMessage("Raw Route '{$raw_route}' already exists: {$this->routes[$raw_route]}, overwriting with {$page_path}");
                }
                $this->routes[$raw_route] = $page_path;
            }

            // add canonical route
            $route_canonical = $page->routeCanonical();
            if ($route_canonical) {
                if (isset($this->routes[$route_canonical]) && $this->routes[$route_canonical] !== $page_path) {
                    $this->grav['debugger']->addMessage("Canonical Route '{$route_canonical}' already exists: {$this->routes[$route_canonical]}, overwriting with {$page_path}");
                }
                $this->routes[$route_canonical] = $page_path;
            }

            // add aliases to routes list if they are provided
            $route_aliases = $page->routeAliases();
            if ($route_aliases) {
                foreach ($route_aliases as $alias) {
                    if (isset($this->routes[$alias]) && $this->routes[$alias] !== $page_path) {
                        $this->grav['debugger']->addMessage("Alias Route '{$alias}' already exists: {$this->routes[$alias]}, overwriting with {$page_path}");
                    }
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
        if (str_starts_with($order_by, 'header.')) {
            $query = explode('|', str_replace('header.', '', $order_by), 2);
            $header_query = array_shift($query) ?? '';
            $header_default = array_shift($query);
        }

        // Load in batches the pages whose sort value isn't in the children index.
        if ($this->index_store) {
            $stored = match ($order_by) {
                'title', 'date', 'modified', 'publish_date', 'slug', 'folder' => $order_by,
                'unpublish_date' => null,
                default => is_string($header_query) ? null : false,
            };
            if ($stored !== false) {
                $load = [];
                foreach ($pages as $key => $info) {
                    if ($stored === null || !isset($info[$stored])) {
                        $load[] = $key;
                    }
                }
                $this->prefetch($load);
            }
        }

        foreach ($pages as $key => $info) {
            // The index entry carries the common sort keys, so load the page only
            // when the value we need isn't already there (header sorts, etc.).
            $meta = is_array($info) ? $info : [];
            $child = null;
            $load = function () use (&$child, $key) {
                if ($child === null) {
                    $child = $this->get($key);
                    if (!$child) {
                        throw new RuntimeException("Page does not exist: {$key}");
                    }
                }

                return $child;
            };

            switch ($order_by) {
                case 'title':
                    $list[$key] = $meta['title'] ?? $load()->title();
                    break;
                case 'date':
                    $list[$key] = $meta['date'] ?? $load()->date();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'modified':
                    $list[$key] = $meta['modified'] ?? $load()->modified();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'publish_date':
                    $list[$key] = $meta['publish_date'] ?? $load()->publishDate();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'unpublish_date':
                    $list[$key] = $load()->unpublishDate();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'slug':
                    $list[$key] = $meta['slug'] ?? $load()->slug();
                    break;
                case 'basename':
                    $list[$key] = Utils::basename($key);
                    break;
                case 'folder':
                    $list[$key] = $meta['folder'] ?? $load()->folder();
                    break;
                case 'manual':
                case 'default':
                default:
                    if (is_string($header_query)) {
                        $child_header = $load()->header();
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
                        $list = preg_replace_callback('~([0-9]+)\.~', static fn($number) => sprintf('%032d.', $number[0]), $list);
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
    protected function arrayShuffle(array $list): array
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
     * Resolve the hash that tells whether the pages changed since they were cached.
     *
     * The check stats the folders and files the last rebuild saw instead of walking the whole
     * tree. A folder's time changes when an entry is added, removed or renamed in it, and a
     * file's time when it is edited, so this finds deleted and renamed pages as well as edits.
     * The result is kept for `system.cache.check.interval` seconds, and while one request
     * checks, the others keep using the last result.
     *
     * @param array $pagesDirs
     * @param int $interval
     * @param string $method
     * @return string|int
     */
    protected function resolvePagesHash(array $pagesDirs, int $interval, string $method): string|int
    {
        if ($method === 'none' || $method === 'off') {
            return 0;
        }

        /** @var Cache $cache */
        $cache = $this->grav['cache'];
        $cacheKey = $this->getPagesHashKey($pagesDirs, $method);
        if ($interval > 0) {
            $cached = $cache->fetch($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $key = md5(json_encode($pagesDirs) . $method);
        $file = $this->getScanFile('check-' . $key);
        $state = $file ? $this->readScanFile($file) : null;
        $known = isset($state['hash'], $state['paths']) && is_array($state['paths']);

        $lock = $interval > 0 ? $this->acquireLock('check-' . $key) : null;
        if ($lock === false && $known) {
            return $state['hash'];
        }

        try {
            if ($known) {
                clearstatcache();
                $current = [];
                $changed = false;
                foreach ($state['paths'] as $path => $modified) {
                    $current[$path] = $now = @filemtime($path);
                    if ($now !== $modified) {
                        $changed = true;
                    }
                }
                $hash = $changed ? $this->hashScanPaths($current) : $state['hash'];
            } else {
                // Nothing scanned yet (or the scan state cannot be saved): walk the tree. A
                // rebuild records the paths it sees, and later checks only stat those.
                $hash = match ($method) {
                    'folder' => Folder::lastModifiedFolder($pagesDirs),
                    'hash' => Folder::hashAllFiles($pagesDirs),
                    default => Folder::lastModifiedFile($pagesDirs),
                };
            }

            if ($interval > 0) {
                $cache->save($cacheKey, $hash, $interval);
            }
        } finally {
            $this->releaseLock($lock);
        }

        return $hash;
    }

    /**
     * Tell Grav that page files changed, so the next request rebuilds the pages cache instead of
     * waiting for the change check to notice. Call it after saving, moving or deleting pages.
     *
     * @return void
     */
    public function markChanged(): void
    {
        // A file rather than the cache driver, so a change made from the CLI (file cache) is
        // seen by web requests using another driver such as APCu.
        $file = $this->getScanFile(static::CHANGE_STAMP_FILE, '');
        if ($file === null) {
            return;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, bin2hex(random_bytes(8))) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }

    /**
     * The stamp markChanged() last wrote, or an empty string if there is none.
     *
     * @return string
     */
    protected function getChangeStamp(): string
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $dir = $locator->findResource('cache://compiled/pages', true, true);
        $stamp = is_string($dir) ? @file_get_contents($dir . '/' . static::CHANGE_STAMP_FILE) : false;

        return is_string($stamp) ? trim($stamp) : '';
    }

    /**
     * @param array $pagesDirs
     * @param string $method
     * @return string
     */
    protected function getPagesHashKey(array $pagesDirs, string $method): string
    {
        return 'pages-hash-' . $method . '-' . md5(json_encode($pagesDirs) . $this->grav['config']->checksum());
    }

    /**
     * @param array<string,int|false> $paths
     * @return string
     */
    protected function hashScanPaths(array $paths): string
    {
        return hash('xxh128', serialize($paths));
    }

    /**
     * @param string $name
     * @param string $extension
     * @return string|null
     */
    protected function getScanFile(string $name, string $extension = '.ser'): ?string
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $dir = $locator->findResource('cache://compiled/pages', true, true);
        if (!is_string($dir) || (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir))) {
            return null;
        }

        return $dir . '/' . $name . $extension;
    }

    /**
     * @param string $file
     * @return array|null
     */
    protected function readScanFile(string $file): ?array
    {
        $raw = @file_get_contents($file);
        $data = $raw ? @unserialize($raw, ['allowed_classes' => false]) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Write a scan file in one step, so a request reading it never sees a partial file.
     *
     * @param string $file
     * @param array $data
     * @return bool
     */
    protected function writeScanFile(string $file, array $data): bool
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, serialize($data)) === false || !@rename($tmp, $file)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    /**
     * Take an exclusive lock, waiting up to $timeout seconds for it.
     *
     * The lock is an flock on a file in cache/compiled/pages, so the operating system releases
     * it when the process that holds it ends, even if that process crashed.
     *
     * @param string $name
     * @param float $timeout Seconds to wait; 0 means do not wait.
     * @param bool $waited Set to true if another process held the lock.
     * @return resource|false|null The lock, false if it is busy, or null if locking is not available.
     */
    protected function acquireLock(string $name, float $timeout = 0, bool &$waited = false)
    {
        $file = $this->getScanFile($name, '.lock');
        $handle = $file ? @fopen($file, 'c') : false;
        if (!$handle) {
            return null;
        }

        $deadline = microtime(true) + $timeout;
        while (true) {
            $wouldBlock = 0;
            if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                return $handle;
            }
            if (!$wouldBlock) {
                // The filesystem does not support locks: carry on without one.
                fclose($handle);

                return null;
            }

            $waited = true;
            if (microtime(true) >= $deadline) {
                fclose($handle);

                return false;
            }
            usleep(25000);
        }
    }

    /**
     * @param resource|false|null $lock
     * @return void
     */
    protected function releaseLock($lock): void
    {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return string
     */
    protected function getVersion(): string
    {
        // 'regular3': the cached tuple gained a lazy-index flag and marker entries,
        // and lazy caches keep routes/children/sort/taxonomy in the index store;
        // the bump makes older and newer Grav versions rebuild instead of
        // misreading each other's cache.
        return $this->directory ? 'flex' : 'regular3';
    }

    /**
     * Get the Pages cache ID
     *
     * this is particularly useful to know if pages have changed and you want
     * to sync another cache with pages cache - works best in `onPagesInitialized()`
     *
     * @return null|string
     */
    public function getPagesCacheId(): ?string
    {
        return $this->pages_cache_id;
    }

    /**
     * Get the simple pages hash that is not md5 encoded, and isn't specific to language
     *
     * @return null|string
     */
    public function getSimplePagesHash(): ?string
    {
        return $this->simple_pages_hash;
    }
}
