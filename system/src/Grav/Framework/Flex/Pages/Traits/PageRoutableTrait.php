<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function is_string;

/**
 * Implements PageRoutableInterface
 */
trait PageRoutableTrait
{
    /** @var bool */
    protected $root = false;

    /** @var string|null */
    private $_route;
    /** @var string|null */
    private $_path;
    /** @var PageInterface|null */
    private $_parentCache;

    /**
     * Returns the page extension, got from the page `url_extension` config and falls back to the
     * system config `system.pages.append_url_extension`.
     *
     * @return string      The extension of this page. For example `.html`
     */
    public function urlExtension(): string
    {
        return $this->loadHeaderProperty(
            'url_extension',
            null,
            function ($value) {
                if ($this->home()) {
                    return '';
                }

                return $value ?? Grav::instance()['config']->get('system.pages.append_url_extension', '');
            }
        );
    }

    /**
     * Gets and Sets whether or not this Page is routable, ie you can reach it via a URL.
     * The page must be *routable* and *published*
     *
     * @param  bool|null $var true if the page is routable
     * @return bool      true if the page is routable
     */
    public function routable($var = null): bool
    {
        $value = $this->loadHeaderProperty(
            'routable',
            $var,
            static function ($value) {
                return $value ?? true;
            }
        );

        return $value && $this->published() && !$this->isModule() && !$this->root() && $this->getLanguages(true);
    }

    /**
     * Gets the URL for a page - alias of url().
     *
     * @param bool $include_host
     * @return string the permalink
     */
    public function link($include_host = false): string
    {
        return $this->url($include_host);
    }

    /**
     * Gets the URL with host information, aka Permalink.
     * @return string The permalink.
     */
    public function permalink(): string
    {
        return $this->url(true, false, true, true);
    }

    /**
     * Returns the canonical URL for a page
     *
     * @param bool $include_lang
     * @return string
     */
    public function canonical($include_lang = true): string
    {
        return $this->url(true, true, $include_lang);
    }

    /**
     * Gets the url for the Page.
     *
     * @param bool $include_host Defaults false, but true would include http://yourhost.com
     * @param bool $canonical true to return the canonical URL
     * @param bool $include_base
     * @param bool $raw_route
     * @return string The url.
     */
    public function url($include_host = false, $canonical = false, $include_base = true, $raw_route = false): string
    {
        // Override any URL when external_url is set
        $external = $this->getNestedProperty('header.external_url');
        if ($external) {
            return $external;
        }

        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        /** @var Config $config */
        $config = $grav['config'];

        // get base route (multi-site base and language)
        $route = $include_base ? $pages->baseRoute() : '';

        // add full route if configured to do so
        if (!$include_host && $config->get('system.absolute_urls', false)) {
            $include_host = true;
        }

        if ($canonical) {
            $route .= $this->routeCanonical();
        } elseif ($raw_route) {
            $route .= $this->rawRoute();
        } else {
            $route .= $this->route();
        }

        /** @var Uri $uri */
        $uri = $grav['uri'];
        $url = $uri->rootUrl($include_host) . '/' . trim($route, '/') . $this->urlExtension();

        return Uri::filterPath($url);
    }

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string $var Set new default route.
     * @return string|null  The route for the Page.
     */
    public function route($var = null): ?string
    {
        if (null !== $var) {
            // TODO: not the best approach, but works...
            $this->setNestedProperty('header.routes.default', $var);
        }

        // Return default route if given.
        $default = $this->getNestedProperty('header.routes.default');
        if (is_string($default)) {
            return $default;
        }

        return $this->routeInternal();
    }

    /**
     * @return string|null
     */
    protected function routeInternal(): ?string
    {
        $route = $this->_route;
        if (null !== $route) {
            return $route;
        }

        if ($this->root()) {
            return null;
        }

        // Root and orphan nodes have no route.
        $parent = $this->parent();
        if (!$parent) {
            return null;
        }

        if ($parent->home()) {
            /** @var Config $config */
            $config = Grav::instance()['config'];
            $hide = (bool)$config->get('system.home.hide_in_urls', false);
            $route = '/' . ($hide ? '' : $parent->slug());
        } else {
            $route = $parent->route();
        }

        if ($route !== '' && $route !== '/') {
            $route .= '/';
        }

        if (!$this->home()) {
            $route .= $this->slug();
        }

        $this->_route = $route;

        return $route;
    }

    /**
     * Helper method to clear the route out so it regenerates next time you use it
     */
    public function unsetRouteSlug(): void
    {
        // TODO:
        throw new RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the page raw route
     *
     * @param string|null $var
     * @return string|null
     */
    public function rawRoute($var = null): ?string
    {
        if (null !== $var) {
            // TODO:
            throw new RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        if ($this->root()) {
            return null;
        }

        return '/' . $this->getKey();
    }

    /**
     * Gets the route aliases for the page based on page headers.
     *
     * @param  array|null $var list of route aliases
     * @return array  The route aliases for the Page.
     */
    public function routeAliases($var = null): array
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routes.aliases', (array)$var);
        }

        $aliases = (array)$this->getNestedProperty('header.routes.aliases');
        $default = $this->getNestedProperty('header.routes.default');
        if ($default) {
            $aliases[] = $default;
        }

        return $aliases;
    }

    /**
     * Gets the canonical route for this page if its set. If provided it will use
     * that value, else if it's `true` it will use the default route.
     *
     * @param string|null $var
     * @return string|null
     */
    public function routeCanonical($var = null): ?string
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routes.canonical', (array)$var);
        }

        $canonical = $this->getNestedProperty('header.routes.canonical');

        return is_string($canonical) ? $canonical : $this->route();
    }

    /**
     * Gets the redirect set in the header.
     *
     * @param  string|null $var redirect url
     * @return string|null
     */
    public function redirect($var = null): ?string
    {
        return $this->loadHeaderProperty(
            'redirect',
            $var,
            static function ($value) {
                return trim($value) ?: null;
            }
        );
    }

    /**
     * Returns the clean path to the page file
     *
     * Needed in admin for Page Media.
     */
    public function relativePagePath(): ?string
    {
        $folder = $this->getMediaFolder();
        if (!$folder) {
            return null;
        }

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $path = $locator->isStream($folder) ? $locator->findResource($folder, false) : $folder;

        return is_string($path) ? $path : null;
    }

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string|null $var the path
     * @return string|null      the path
     */
    public function path($var = null): ?string
    {
        if (null !== $var) {
            // TODO:
            throw new RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        $path = $this->_path;
        if ($path) {
            return $path;
        }

        if ($this->root()) {
            $folder = $this->getFlexDirectory()->getStorageFolder();
        } else {
            $folder = $this->getStorageFolder();
        }

        if ($folder) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $folder = $locator->isStream($folder) ? $locator->getResource($folder) : GRAV_ROOT . "/{$folder}";
        }

        return $this->_path = is_string($folder) ? $folder : null;
    }

    /**
     * Get/set the folder.
     *
     * @param string|null $var Optional path, including numeric prefix.
     * @return string|null
     */
    public function folder($var = null): ?string
    {
        return $this->loadProperty(
            'folder',
            $var,
            function ($value) {
                if (null === $value) {
                    $value = $this->getMasterKey() ?: $this->getKey();
                }

                return Utils::basename($value) ?: null;
            }
        );
    }

    /**
     * Get/set the folder.
     *
     * @param string|null $var Optional path, including numeric prefix.
     * @return string|null
     */
    public function parentStorageKey($var = null): ?string
    {
        return $this->loadProperty(
            'parent_key',
            $var,
            function ($value) {
                if (null === $value) {
                    $filesystem = Filesystem::getInstance(false);
                    $value = $this->getMasterKey() ?: $this->getKey();
                    $value = ltrim($filesystem->dirname("/{$value}"), '/') ?: '';
                }

                return $value;
            }
        );
    }

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  PageInterface|null $var the parent page object
     * @return PageInterface|null the parent page object if it exists.
     */
    public function parent(PageInterface $var = null)
    {
        if (null !== $var) {
            // TODO:
            throw new RuntimeException(__METHOD__ . '(PageInterface): Not Implemented');
        }

        if ($this->_parentCache || $this->root()) {
            return $this->_parentCache;
        }

        // Use filesystem as \dirname() does not work in Windows because of '/foo' becomes '\'.
        $filesystem = Filesystem::getInstance(false);
        $directory = $this->getFlexDirectory();
        $parentKey = ltrim($filesystem->dirname("/{$this->getKey()}"), '/');
        if ('' !== $parentKey) {
            $parent = $directory->getObject($parentKey);
            $language = $this->getLanguage();
            if ($language && $parent && method_exists($parent, 'getTranslation')) {
                $parent = $parent->getTranslation($language) ?? $parent;
            }

            $this->_parentCache = $parent;
        } else {
            $index = $directory->getIndex();

            $this->_parentCache = \is_callable([$index, 'getRoot']) ? $index->getRoot() : null;
        }

        return $this->_parentCache;
    }

    /**
     * Gets the top parent object for this page. Can return page itself.
     *
     * @return PageInterface The top parent page object.
     */
    public function topParent()
    {
        $topParent = $this;
        while ($topParent) {
            $parent = $topParent->parent();
            if (!$parent || !$parent->parent()) {
                break;
            }
            $topParent = $parent;
        }

        return $topParent;
    }

    /**
     * Returns the item in the current position.
     *
     * @return int|null   the index of the current page.
     */
    public function currentPosition(): ?int
    {
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if ($collection instanceof PageCollectionInterface && $path = $this->path()) {
            return $collection->currentPosition($path);
        }

        return 1;
    }

    /**
     * Returns whether or not this page is the currently active page requested via the URL.
     *
     * @return bool True if it is active
     */
    public function active(): bool
    {
        $grav = Grav::instance();
        $uri_path = rtrim(urldecode($grav['uri']->path()), '/') ?: '/';
        $routes = $grav['pages']->routes();

        return isset($routes[$uri_path]) && $routes[$uri_path] === $this->path();
    }

    /**
     * Returns whether or not this URI's URL contains the URL of the active page.
     * Or in other words, is this page's URL in the current URL
     *
     * @return bool True if active child exists
     */
    public function activeChild(): bool
    {
        $grav = Grav::instance();
        /** @var Uri $uri */
        $uri = $grav['uri'];
        /** @var Pages $pages */
        $pages = $grav['pages'];
        $uri_path = rtrim(urldecode($uri->path()), '/');
        $routes = $pages->routes();

        if (isset($routes[$uri_path])) {
            $page = $pages->find($uri->route());
            /** @var PageInterface|null $child_page */
            $child_page = $page ? $page->parent() : null;
            while ($child_page && !$child_page->root()) {
                if ($this->path() === $child_page->path()) {
                    return true;
                }
                $child_page = $child_page->parent();
            }
        }

        return false;
    }

    /**
     * Returns whether or not this page is the currently configured home page.
     *
     * @return bool True if it is the homepage
     */
    public function home(): bool
    {
        $home = Grav::instance()['config']->get('system.home.alias');

        return '/' . $this->getKey() === $home;
    }

    /**
     * Returns whether or not this page is the root node of the pages tree.
     *
     * @param bool|null $var
     * @return bool True if it is the root
     */
    public function root($var = null): bool
    {
        if (null !== $var) {
            $this->root = (bool)$var;
        }

        return $this->root === true || $this->getKey() === '/';
    }
}
