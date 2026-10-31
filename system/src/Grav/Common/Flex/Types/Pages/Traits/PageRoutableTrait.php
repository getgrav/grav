<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages\Traits;

use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use RuntimeException;

/**
 * Implements PageRoutableInterface.
 */
trait PageRoutableTrait
{
    /**
     * Gets and Sets the parent object for this page
     *
     * @param  PageInterface|null $var the parent page object
     * @return PageInterface|null the parent page object if it exists.
     */

    public function parent(?PageInterface $var = null)
    {
        if (Utils::isAdminPlugin()) {
            return parent::parent();
        }

        if (null !== $var) {
            throw new RuntimeException('Not Implemented');
        }

        if ($this->root()) {
            return null;
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        $filesystem = Filesystem::getInstance(false);

        // Parent is the folder the page is in. Its route does not tell which one that is: any page can claim a route.
        $path = $this->path();
        $parent = null !== $path ? $pages->get($filesystem->dirname($path)) : null;

        // New pages have no folder yet, and a folder which shares its raw route with another one is not in pages.
        return $parent ?? parent::parent();
    }

    /**
     * Returns the item in the current position.
     *
     * @return int|null   the index of the current page.
     */
    public function currentPosition(): ?int
    {
        $path = $this->path();
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if (null !== $path && $collection instanceof PageCollectionInterface) {
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
}
