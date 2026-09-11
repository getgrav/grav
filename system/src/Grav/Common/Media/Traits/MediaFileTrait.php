<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Trait MediaFileTrait
 * @package Grav\Common\Media\Traits
 */
trait MediaFileTrait
{
    /**
     * Check if this medium exists or not
     *
     * @return bool
     */
    public function exists()
    {
        $path = $this->path(false);

        return file_exists($path);
    }

    /**
     * Get file modification time for the medium.
     *
     * @return int|null
     */
    public function modified()
    {
        $path = $this->path(false);
        if (!file_exists($path)) {
            return null;
        }

        return filemtime($path) ?: null;
    }

    /**
     * Get size of the medium.
     *
     * @return int
     */
    public function size()
    {
        $path = $this->path(false);
        if (!file_exists($path)) {
            return 0;
        }

        return filesize($path) ?: 0;
    }

    /**
     * Return PATH to file.
     *
     * @param bool $reset
     * @return string path to file
     */
    public function path($reset = true)
    {
        if ($reset) {
            $this->reset();
        }

        return $this->get('url') ?? $this->get('filepath');
    }

    /**
     * Return the relative path to file
     *
     * @param bool $reset
     * @return string
     */
    public function relativePath($reset = true)
    {
        if ($reset) {
            $this->reset();
        }

        $path = $this->path(false);
        $output = preg_replace('|^' . preg_quote(GRAV_ROOT, '|') . '|', '', (string) $path) ?: $path;

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        if ($locator->isStream($output)) {
            $output = (string)($locator->findResource($output, false) ?: $locator->findResource($output, false, true));
        }

        return $output;
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @param bool $include_host Prepend the scheme and host, as `page.url(true)` does
     * @return string
     */
    public function url($reset = true, $include_host = false)
    {
        $url = $this->get('url');
        if ($url) {
            return $this->withHost((string)$url, $include_host);
        }

        $path = $this->relativePath($reset);

        return $this->withHost(trim($this->getGrav()['base_url'] . '/' . $this->urlQuerystring($path), '\\'), $include_host);
    }

    /**
     * Prepend the scheme and host to a root-relative URL when `$include_host` is
     * set, the way `page.url(true)` builds its URL.
     *
     * A root-relative URL, the file's own or a `url` override (the page route
     * from `pages.media_route_urls`, or a media proxy), already carries the
     * site's base path, so only the host is added. `Uri::base()` is also where
     * a `custom_base_url` host lands. Absolute and protocol-relative URLs are
     * returned as they are.
     *
     * @param string $url
     * @param bool $include_host
     * @return string
     */
    protected function withHost(string $url, $include_host): string
    {
        if ($include_host && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $this->getGrav()['uri']->base() . $url;
        }

        return $url;
    }

    /**
     * Get the URL with full querystring
     *
     * @param string $url
     * @return string
     */
    abstract public function urlQuerystring($url);

    /**
     * Reset medium.
     *
     * @return $this
     */
    abstract public function reset();

    /**
     * @return Grav
     */
    abstract protected function getGrav(): Grav;
}
