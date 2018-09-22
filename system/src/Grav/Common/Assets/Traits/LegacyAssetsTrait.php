<?php
/**
 * @package    Grav.Common.Assets.Traits
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets\Traits;

trait LegacyAssetsTrait
{

    protected $timestamp;

    protected function unifyLegacyArguments($args)
    {
        $arguments = [];

        // First argument is always the asset
        array_shift($args);

        if (count($args) === 0) {
            return [];
        } elseif (count($args) === 1 && is_array($args[0])) {
            return $args[0];
        }

        // $asset, $priority = null, $pipeline = true, $group = null, $loading = null

        foreach ($args as $index => $arg) {
            switch ($index) {
                case 0:
                    $arguments['priority'] = $args[0] ?? null;
                    break;
                case 1:
                    $arguments['pipeline'] = $args[1] ?? null;
                    break;
                case 2:
                    $arguments['group'] = $args[2] ?? null;
                    break;
                case 3:
                    $arguments['loading'] = $args[3] ?? null;
                    break;
            }
        }

        return $arguments;
    }

    /**
     * Determines if an asset exists as a collection, CSS or JS reference
     *
     * @param $asset
     *
     * @return bool
     */
    public function exists($asset)
    {
        return true;
    }

    /**
     * Return the array of all the registered collections
     *
     * @return array
     */
    public function getCollections()
    {
        return [];
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
        return $this;
    }

    /**
     * Add/replace collection.
     *
     * @param  string $collectionName
     * @param  array  $assets
     * @param bool    $overwrite
     *
     * @return $this
     */
    public function registerCollection($collectionName, Array $assets, $overwrite = false)
    {
        return $this->addCollection($collectionName, $assets, $overwrite);
    }

    /**
     * Add an asset or a collection of assets.
     *
     * It automatically detects the asset type (JavaScript, CSS or collection).
     * You may add more than one asset passing an array as argument.
     *
     * @param  mixed $asset
     * @param  int   $priority the priority, bigger comes first
     * @param  bool  $pipeline false if this should not be pipelined
     *
     * @return $this
     */
    public function _legacyAdd($asset, $priority = null, $pipeline = true)
    {

        return $this;
    }

    /**
     * Add a CSS asset.
     *
     * It checks for duplicates.
     * You may add more than one asset passing an array as argument.
     * The second argument may alternatively contain an array of options which take precedence over positional
     * arguments.
     *
     * @param  mixed   $asset
     * @param  int     $priority the priority, bigger comes first
     * @param  bool    $pipeline false if this should not be pipelined
     * @param  string  $group
     * @param  string  $loading  how the asset is loaded (async/defer/inline, for CSS: only inline)
     *
     * @return $this
     */
    protected function _legacyAddCss($asset, $priority = null, $pipeline = true, $group = null, $loading = null)
    {
        return $this;
    }

    /**
     * Add an inline CSS asset.
     *
     * It checks for duplicates.
     * For adding chunks of string-based inline CSS
     *
     * @param  mixed $asset
     * @param  int   $priority the priority, bigger comes first
     * @param null   $group
     *
     * @return $this
     */
    protected function _legacyAddInlineCss($asset, $priority = null, $group = null)
    {
        return $this;
    }

    /**
     * Add a JavaScript asset.
     *
     * It checks for duplicates.
     * You may add more than one asset passing an array as argument.
     * The second argument may alternatively contain an array of options which take precedence over positional
     * arguments.
     *
     * @param  mixed  $asset
     * @param  int    $priority the priority, bigger comes first
     * @param  bool   $pipeline false if this should not be pipelined
     * @param  string $loading  how the asset is loaded (async/defer)
     * @param  string $group    name of the group
     *
     * @return $this
     */
    protected function _legacyAddJs($asset, $priority = null, $pipeline = true, $loading = null, $group = null)
    {
        return $this;
    }


    /**
     * Add an inline JS asset.
     *
     * It checks for duplicates.
     * For adding chunks of string-based inline JS
     *
     * @param  mixed $asset
     * @param  int $priority the priority, bigger comes first
     * @param string $group name of the group
     * @param null $attributes
     *
     * @return $this
     */
    protected function _addLegacyInlineJs($asset, $priority = null, $group = null, $attributes = null)
    {
        return $this;
    }


    /**
     * Convenience wrapper for async loading of JavaScript
     *
     * @param        $asset
     * @param int    $priority
     * @param bool   $pipeline
     * @param string $group name of the group
     *
     * @deprecated Please use dynamic method with ['loading' => 'async']
     *
     * @return \Grav\Common\Assets
     */
    public function addAsyncJs($asset, $priority = null, $pipeline = true, $group = null)
    {
        return $this;
    }

    /**
     * Convenience wrapper for deferred loading of JavaScript
     *
     * @param        $asset
     * @param int    $priority
     * @param bool   $pipeline
     * @param string $group name of the group
     *
     * @deprecated Please use dynamic method with ['loading' => 'defer']
     *
     * @return \Grav\Common\Assets
     */
    public function addDeferJs($asset, $priority = null, $pipeline = true, $group = null)
    {
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
        return $this;
    }

    /**
     * Reset JavaScript assets.
     *
     * @return $this
     */
    public function resetJs()
    {
        return $this;
    }

    /**
     * Reset CSS assets.
     *
     * @return $this
     */
    public function resetCss()
    {

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
        return;
    }

    /**
     *
     *
     * @param $asset
     * @return string
     */
    public function getQuerystring($asset)
    {
        $querystring = '';

        if (!empty($asset['query'])) {
            if (Utils::contains($asset['asset'], '?')) {
                $querystring .=  '&' . $asset['query'];
            } else {
                $querystring .= '?' . $asset['query'];
            }
        }

        if ($this->timestamp) {
            if (Utils::contains($asset['asset'], '?') || $querystring) {
                $querystring .=  '&' . $this->timestamp;
            } else {
                $querystring .= '?' . $this->timestamp;
            }
        }

        return $querystring;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return '';
    }
}
