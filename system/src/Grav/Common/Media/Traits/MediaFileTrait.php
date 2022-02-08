<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
        return $this->get('modified');
    }

    /**
     * Get size of the medium.
     *
     * @return int
     */
    public function size()
    {
        return $this->get('size');
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
        $output = preg_replace('|^' . preg_quote(GRAV_WEBROOT, '|') . '|', '', $path) ?: $path;

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
     * @return string
     */
    public function url($reset = true)
    {
        $url = $this->get('url');
        if ($url) {
            return $url;
        }

        $path = $this->relativePath($reset);

        return trim($this->getGrav()['base_url'] . '/' . $this->urlQuerystring($path), '\\');
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
