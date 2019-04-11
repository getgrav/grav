<?php

/**
 * @package    Grav\Common\Assets\Traits
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets\Traits;

use Grav\Common\Grav;

trait TestingAssetsTrait
{
    /**
     * Determines if an asset exists as a collection, CSS or JS reference
     *
     * @param string $asset
     *
     * @return bool
     */
    public function exists($asset)
    {
        return isset($this->collections[$asset]) || isset($this->assets_css[$asset]) || isset($this->assets_js[$asset]);
    }

    /**
     * Return the array of all the registered collections
     *
     * @return array
     */
    public function getCollections()
    {
        return $this->collections;
    }

    /**
     * Set the array of collections explicitly
     *
     * @param array $collections
     *
     * @return $this
     */
    public function setCollection($collections)
    {
        $this->collections = $collections;

        return $this;
    }

    /**
     * Return the array of all the registered CSS assets
     * If a $key is provided, it will try to return only that asset
     * else it will return null
     *
     * @param null|string $key the asset key
     * @return array
     */
    public function getCss($key = null)
    {
        if (null !== $key) {
            $asset_key = md5($key);

            return $this->assets_css[$asset_key] ?? null;
        }

        return $this->assets_css;
    }

    /**
     * Return the array of all the registered JS assets
     * If a $key is provided, it will try to return only that asset
     * else it will return null
     *
     * @param null|string $key the asset key
     * @return array
     */
    public function getJs($key = null)
    {
        if (null !== $key) {
            $asset_key = md5($key);

            return $this->assets_js[$asset_key] ?? null;
        }

        return $this->assets_js;
    }

    /**
     * Set the whole array of CSS assets
     *
     * @param array $css
     *
     * @return $this
     */
    public function setCss($css)
    {
        $this->assets_css = $css;

        return $this;
    }

    /**
     * Set the whole array of JS assets
     *
     * @param array $js
     *
     * @return $this
     */
    public function setJs($js)
    {
        $this->assets_js = $js;

        return $this;
    }

    /**
     * Removes an item from the CSS array if set
     *
     * @param string $key  The asset key
     *
     * @return $this
     */
    public function removeCss($key)
    {
        $asset_key = md5($key);
        if (isset($this->assets_css[$asset_key])) {
            unset($this->assets_css[$asset_key]);
        }

        return $this;
    }

    /**
     * Removes an item from the JS array if set
     *
     * @param string $key  The asset key
     *
     * @return $this
     */
    public function removeJs($key)
    {
        $asset_key = md5($key);
        if (isset($this->assets_js[$asset_key])) {
            unset($this->assets_js[$asset_key]);
        }

        return $this;
    }

    /**
     * Sets the state of CSS Pipeline
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCssPipeline($value)
    {
        $this->css_pipeline = (bool)$value;

        return $this;
    }

    /**
     * Sets the state of JS Pipeline
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setJsPipeline($value)
    {
        $this->js_pipeline = (bool)$value;

        return $this;
    }

    /**
     * Reset all assets.
     *
     * @return $this
     */
    public function reset()
    {
        $this->resetCss();
        $this->resetJs();
        $this->setCssPipeline(false);
        $this->setJsPipeline(false);

        return $this;
    }

    /**
     * Reset JavaScript assets.
     *
     * @return $this
     */
    public function resetJs()
    {
        $this->assets_js = [];

        return $this;
    }

    /**
     * Reset CSS assets.
     *
     * @return $this
     */
    public function resetCss()
    {
        $this->assets_css = [];

        return $this;
    }

    /**
     * Explicitly set's a timestamp for assets
     *
     * @param string|int $value
     */
    public function setTimestamp($value)
    {
        $this->timestamp = $value;
    }

    /**
     * Get the timestamp for assets
     *
     * @param  bool  $include_join
     * @return string
     */
    public function getTimestamp($include_join = true)
    {
        if ($this->timestamp) {
            return $include_join ? '?' . $this->timestamp : $this->timestamp;
        }

        return null;
    }

    /**
     * Add all assets matching $pattern within $directory.
     *
     * @param  string $directory Relative to the Grav root path, or a stream identifier
     * @param  string $pattern   (regex)
     *
     * @return $this
     */
    public function addDir($directory, $pattern = self::DEFAULT_REGEX)
    {
        $root_dir = rtrim(ROOT_DIR, '/');

        // Check if $directory is a stream.
        if (strpos($directory, '://')) {
            $directory = Grav::instance()['locator']->findResource($directory, null);
        }

        // Get files
        $files = $this->rglob($root_dir . DIRECTORY_SEPARATOR . $directory, $pattern, $root_dir . '/');

        // No luck? Nothing to do
        if (!$files) {
            return $this;
        }

        // Add CSS files
        if ($pattern === self::CSS_REGEX) {
            foreach ($files as $file) {
                $this->addCss($file);
            }

            return $this;
        }

        // Add JavaScript files
        if ($pattern === self::JS_REGEX) {
            foreach ($files as $file) {
                $this->addJs($file);
            }

            return $this;
        }

        // Unknown pattern.
        foreach ($files as $asset) {
            $this->add($asset);
        }

        return $this;
    }

    /**
     * Add all JavaScript assets within $directory
     *
     * @param  string $directory Relative to the Grav root path, or a stream identifier
     *
     * @return $this
     */
    public function addDirJs($directory)
    {
        return $this->addDir($directory, self::JS_REGEX);
    }

    /**
     * Add all CSS assets within $directory
     *
     * @param  string $directory Relative to the Grav root path, or a stream identifier
     *
     * @return $this
     */
    public function addDirCss($directory)
    {
        return $this->addDir($directory, self::CSS_REGEX);
    }

    /**
     * Recursively get files matching $pattern within $directory.
     *
     * @param  string $directory
     * @param  string $pattern (regex)
     * @param  string $ltrim   Will be trimmed from the left of the file path
     *
     * @return array
     */
    protected function rglob($directory, $pattern, $ltrim = null)
    {
        $iterator = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,
            \FilesystemIterator::SKIP_DOTS)), $pattern);
        $offset = \strlen($ltrim);
        $files = [];

        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), $offset);
        }

        return $files;
    }


}
