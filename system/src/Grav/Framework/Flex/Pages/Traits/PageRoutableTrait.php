<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Implements PageRoutableInterface
 */
trait PageRoutableTrait
{
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
     * @param  bool $var true if the page is routable
     *
     * @return bool      true if the page is routable
     */
    public function routable($var = null): bool
    {
        $value = $this->loadHeaderProperty(
            'routable',
            $var,
            function ($value) {
                return ($value ?? true) && $this->published() && $this->getLanguages(true);
            }
        );

        return $value && $this->published() && !$this->modular();
    }

    /**
     * Gets the URL for a page - alias of url().
     *
     * @param bool $include_host
     *
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
     *
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
     *
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

        // trim trailing / if not root
        if ($url !== '/') {
            $url = rtrim($url, '/');
        }

        return Uri::filterPath($url);
    }

    /**
     * Gets the route for the page based on the route headers if available, else from
     * the parents route and the current Page's slug.
     *
     * @param  string $var Set new default route.
     *
     * @return string|null  The route for the Page.
     */
    public function route($var = null): ?string
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        // TODO: implement rest of the routing:
        return $this->rawRoute();
    }

    /**
     * Helper method to clear the route out so it regenerates next time you use it
     */
    public function unsetRouteSlug(): void
    {
        // TODO:
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Gets and Sets the page raw route
     *
     * @param string|null $var
     *
     * @return string|null
     */
    public function rawRoute($var = null): ?string
    {
        if (null !== $var) {
            // TODO:
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        // TODO: missing full implementation
        return '/' . $this->getKey();
    }

    /**
     * Gets the route aliases for the page based on page headers.
     *
     * @param  array $var list of route aliases
     *
     * @return array  The route aliases for the Page.
     */
    public function routeAliases($var = null): array
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routes.aliases', (array)$var);
        }

        // FIXME: check route() logic of Page
        return (array)$this->getNestedProperty('header.routes.aliases');
    }

    /**
     * Gets the canonical route for this page if its set. If provided it will use
     * that value, else if it's `true` it will use the default route.
     *
     * @param string|null $var
     *
     * @return string
     */
    public function routeCanonical($var = null): string
    {
        if (null !== $var) {
            $this->setNestedProperty('header.routes.canonical', (array)$var);
        }

        return $this->getNestedProperty('header.routes.canonical', $this->route());
    }

    /**
     * Gets the redirect set in the header.
     *
     * @param  string $var redirect url
     *
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

        return $locator->findResource($folder, false);
    }

    /**
     * Gets and sets the path to the folder where the .md for this Page object resides.
     * This is equivalent to the filePath but without the filename.
     *
     * @param  string $var the path
     *
     * @return string|null      the path
     */
    public function path($var = null): ?string
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(string): Not Implemented');
        }

        if ($this->root()) {
            $folder = $this->getFlexDirectory()->getStorageFolder();
        } else {
            $folder = $this->getStorageFolder();
        }

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $folder ? $locator($folder) : null;
    }

    /**
     * Get/set the folder.
     *
     * @param string $var Optional path, including numeric prefix.
     *
     * @return string|null
     */
    public function folder($var = null): ?string
    {
        return $this->loadProperty(
            'folder',
            $var,
            function ($value) {
                if (null === $value) {
                    $value = $this->getStorageKey(true) ?: $this->getKey();
                }

                return basename($value) ?: null;
            }
        );
    }

    /**
     * Get/set the folder.
     *
     * @param string $var Optional path, including numeric prefix.
     *
     * @return string|null
     */
    public function parentStorageKey($var = null): ?string
    {
        return $this->loadProperty(
            'parent_key',
            $var,
            function ($value) {
                if (null === $value) {
                    $value = $this->getStorageKey(true) ?: $this->getKey();
                    $value = ltrim(dirname("/{$value}"), '/') ?: '';
                }

                return $value;
            }
        );
    }

    /**
     * Gets and Sets the parent object for this page
     *
     * @param  PageInterface $var the parent page object
     *
     * @return PageInterface|null the parent page object if it exists.
     */
    public function parent(PageInterface $var = null)
    {
        // TODO:
        if (null !== $var) {
            throw new \RuntimeException(__METHOD__ . '(PageInterface): Not Implemented');
        }

        $parentKey = ltrim(dirname("/{$this->getKey()}"), '/');

        return $parentKey ? $this->getFlexDirectory()->getObject($parentKey) : $this->getFlexDirectory()->getIndex()->getRoot();
    }

    /**
     * Gets the top parent object for this page
     *
     * @return PageInterface|null the top parent page object if it exists.
     */
    public function topParent()
    {
        $topParent = $this->parent();
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
        if ($collection instanceof PageCollectionInterface) {
            return $collection->currentPosition($this->path());
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
        $uri = $grav['uri'];
        $pages = $grav['pages'];
        $uri_path = rtrim(urldecode($uri->path()), '/');
        $routes = $pages->routes();

        if (isset($routes[$uri_path])) {
            /** @var PageInterface $child_page */
            $child_page = $pages->dispatch($uri->route())->parent();
            if ($child_page) {
                while (!$child_page->root()) {
                    if ($this->path() === $child_page->path()) {
                        return true;
                    }
                    $child_page = $child_page->parent();
                }
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
     * @return bool True if it is the root
     */
    public function root(): bool
    {
        return $this->getKey() === '/';
    }

    abstract protected function loadHeaderProperty(string $property, $var, callable $filter);
}
