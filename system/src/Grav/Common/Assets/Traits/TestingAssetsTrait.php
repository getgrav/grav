<?php
/**
 * @package    Grav.Common.Assets.Traits
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets\Traits;

trait TestingAssetsTrait
{
    /**
     * Determines if an asset exists as a collection, CSS or JS reference
     *
     * @param $asset
     *
     * @return bool
     */
    public function exists($asset)
    {
        if (isset($this->collections[$asset]) || isset($this->assets_css[$asset]) || isset($this->assets_js[$asset])) {
            return true;
        } else {
            return false;
        }
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
     * @param $collections
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
        return [];
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
        return [];
    }

    /**
     * Set the whole array of CSS assets
     *
     * @param $css
     *
     * @return $this
     */
    public function setCss($css)
    {
        return $this;
    }

    /**
     * Set the whole array of JS assets
     *
     * @param $js
     *
     * @return $this
     */
    public function setJs($js)
    {
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
        return $this;
    }

    /**
     * Sets the state of CSS Pipeline
     *
     * @param boolean $value
     *
     * @return $this
     */
    public function setCssPipeline($value)
    {
        return $this;
    }

    /**
     * Sets the state of JS Pipeline
     *
     * @param boolean $value
     *
     * @return $this
     */
    public function setJsPipeline($value)
    {
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
     * @param $value
     */
    public function setTimestamp($value)
    {
        $this->timestamp = $value;
    }

    /**
     * Get the timestamp for assets
     *
     * @return string
     */
    public function getTimestamp($include_join = true)
    {
        if ($this->timestamp) {
            $timestamp = $include_join ? '?' . $this->timestamp : $this->timestamp;
            return $timestamp;
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
     * @throws Exception
     */
    public function addDir($directory, $pattern = self::DEFAULT_REGEX)
    {

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


}
