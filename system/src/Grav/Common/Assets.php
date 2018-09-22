<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;


use FilesystemIterator;
use Grav\Common\Assets\Traits\LegacyAssets;
use Grav\Common\Config\Config;
use Grav\Framework\Object\PropertyObject;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;


class Assets extends PropertyObject
{
    use LegacyAssets;

    /** @const Regex to match CSS and JavaScript files */
    const DEFAULT_REGEX = '/.\.(css|js)$/i';

    /** @const Regex to match CSS files */
    const CSS_REGEX = '/.\.css$/i';

    /** @const Regex to match JavaScript files */
    const JS_REGEX = '/.\.js$/i';

    /**
     * @const Regex to match <script> or <style> tag when adding inline style/script. Note that this only supports a
     * single tag, so the check is greedy to avoid issues in JS.
     */
    const HTML_TAG_REGEX = '#(<([A-Z][A-Z0-9]*)>)+(.*)(<\/\2>)#is';

    protected $assets_dir;
    protected $assets_url;
    protected $base_url;

    // Config Options
    protected $css_pipeline;
    protected $js_pipeline;

    protected $fetch_command;
    protected $autoload;
    protected $enable_asset_timestamp;

    protected $pipeline_options = [];


    /**
     * Initialization called in the Grav lifecycle to initialize the Assets with appropriate configuration
     */
    public function init()
    {
        $grav = Grav::instance();
        /** @var Config $config */
        $config = $grav['config'];
        $base_url = $grav['base_url'];
        $asset_config = (array)$config->get('system.assets');

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $this->assets_dir = $locator->findResource('asset://') . DS;
        $this->assets_url = $locator->findResource('asset://', false);

        $this->config($asset_config);
        $this->base_url = ($config->get('system.absolute_urls') ? '' : '/') . ltrim(ltrim($base_url, '/') . '/', '/');

        // Register any preconfigured collections
        $collections = $config->get('system.assets.collections', []);
        foreach ((array) $collections as $name => $collection) {
            $this->addCollection($name, (array)$collection);
        }
    }

    /**
     * Set up configuration options.
     *
     * All the class properties except 'js' and 'css' are accepted here.
     * Also, an extra option 'autoload' may be passed containing an array of
     * assets and/or collections that will be automatically added on startup.
     *
     * @param  array $config Configurable options.
     *
     * @return $this
     */
    public function config(array $config)
    {
        foreach ($config as $key => $value) {
            if ($this->hasProperty($key)) {
                $this->setProperty($key, $value);
            } elseif (Utils::startsWith($key, 'css_') || Utils::startsWith($key, 'js_')) {
                $this->pipeline_options[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Handle method overrides
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public function __call($method, $args) {

        switch ($method) {
            case 'add':
                if (!$this->isLegacyAddCall($args)) {
                    return $this->_add($args[0], $args[1] ?? null);
                } else {
                    return $this->_legacyAdd($args[0], $args[1] ?? null,  $args[2] ?? null);
                }
                break;

            case 'addCss':
                if (!$this->isLegacyAddCall($args)) {
                    return $this->_addCss($args[0], $args[1] ?? null);
                } else {
                    return $this->_legacyAddCss($args[0], $args[1] ?? null,  $args[2] ?? null, $args[3] ?? null, $args[4] ?? null);
                }
                break;

            case 'addInlineCss':
                if (!$this->isLegacyAddCall($args)) {
                    return $this->_addInlineCss($args[0], $args[1] ?? null);
                } else {
                    return $this->_legacyAddInlineCss($args[0], $args[1] ?? null,  $args[2] ?? null);
                }
                break;


            case 'addJs':
                if (!$this->isLegacyAddCall($args)) {
                    return $this->_addJs($args[0], $args[1] ?? null);
                } else {
                    return $this->_legacyAddJs($args[0], $args[1] ?? null,  $args[2] ?? null, $args[3] ?? null, $args[4] ?? null);
                }
                break;

            case 'addInlineJs':
                if (!$this->isLegacyAddCall($args)) {
                    return $this->_addInlineJs($args[0], $args[1] ?? null);
                } else {
                    return $this->_legacyAddInlineJs($args[0], $args[1] ?? null,  $args[2] ?? null);
                }
                break;

        }
    }

    /**
     * Rules to determine if this should be a legacy call based on number of args
     *
     * @param $args
     * @return bool
     */
    protected function isLegacyAddCall($args)
    {
        if (count($args) === 1 || (count($args) === 2 && is_array($args[1]))) {
            return false;
        }
        return true;
    }


    /**
     * Add an asset or a collection of assets.
     *
     * It automatically detects the asset type (JavaScript, CSS or collection).
     * You may add more than one asset passing an array as argument.
     *
     * @param  mixed $asset
     *
     * @return $this
     */
    protected function _add($asset, $options = null)
    {
        return $this;
    }

    /**
     * Add a CSS asset or a collection of assets.
     *
     * @param  mixed $asset
     *
     * @return $this
     */
    protected function _addCss($asset, $options = null)
    {

        return $this;
    }

    /**
     * Add an Inline CSS asset or a collection of assets.
     *
     * @param  mixed $asset
     *
     * @return $this
     */
    protected function _addInlineCss($asset, $options = null)
    {

        return $this;
    }

    /**
     * Add a JS asset or a collection of assets.
     *
     * @param  mixed $asset
     *
     * @return $this
     */
    protected function _addJs($asset, $options = null)
    {
        return $this;
    }

    /**
     * Add an Inline JS asset or a collection of assets.
     *
     * @param  mixed $asset
     *
     * @return $this
     */
    protected function _addInlineJs($asset, $options = null)
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
    public function addCollection($collectionName, Array $assets, $overwrite = false)
    {

    }


    /**
     * Build the CSS link tags.
     *
     * @param  string $group name of the group
     * @param  array  $attributes
     *
     * @return string
     */
    public function css($group = 'head', $attributes = [])
    {

    }

    /**
     * Build the JavaScript script tags.
     *
     * @param  string $group name of the group
     * @param  array  $attributes
     *
     * @return string
     */
    public function js($group = 'head', $attributes = [])
    {

    }











    /**
     * TODO: Do we need?
     *
     * Determine whether a link is local or remote.
     *
     * Understands both "http://" and "https://" as well as protocol agnostic links "//"
     *
     * @param  string $link
     *
     * @return bool
     */
    protected function isRemoteLink($link)
    {
        $base = Grav::instance()['uri']->rootUrl(true);

        // sanity check for local URLs with absolute URL's enabled
        if (Utils::startsWith($link, $base)) {
            return false;
        }

        return ('http://' === substr($link, 0, 7) || 'https://' === substr($link, 0, 8) || '//' === substr($link, 0,
                2));
    }

    /**
     * TODO: Do we need?
     *
     * Build local links including grav asset shortcodes
     *
     * @param  string $asset    the asset string reference
     * @param  bool   $absolute build absolute asset link
     *
     * @return string           the final link url to the asset
     */
    protected function buildLocalLink($asset, $absolute = false)
    {
        try {
            $asset = Grav::instance()['locator']->findResource($asset, $absolute);
        } catch (\Exception $e) {
        }

        $uri = $absolute ? $asset : $this->base_url . ltrim($asset, '/');
        return $asset ? $uri : false;
    }

    /**
     * TODO: Do we need?
     *
     * Get the last modification time of asset
     *
     * @param  string $asset    the asset string reference
     *
     * @return string           the last modifcation time or false on error
     */
    protected function getLastModificationTime($asset)
    {
        $file = GRAV_ROOT . $asset;
        if (Grav::instance()['locator']->isStream($asset)) {
            $file = $this->buildLocalLink($asset, true);
        }

        return file_exists($file) ? filemtime($file) : false;
    }

    /**
     * TODO: Do we need?
     *
     * Build an HTML attribute string from an array.
     *
     * @param  array $attributes
     *
     * @return string
     */
    protected function attributes(array $attributes)
    {
        $html = '';
        $no_key = ['loading'];

        foreach ($attributes as $key => $value) {
            // For numeric keys we will assume that the key and the value are the same
            // as this will convert HTML attributes such as "required" to a correct
            // form like required="required" instead of using incorrect numerics.
            if (is_numeric($key)) {
                $key = $value;
            }
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            if (in_array($key, $no_key)) {
                $element = htmlentities($value, ENT_QUOTES, 'UTF-8', false);
            } else {
                $element = $key . '="' . htmlentities($value, ENT_QUOTES, 'UTF-8', false) . '"';
            }

            $html .= ' ' . $element;
        }

        return $html;
    }


    /**
     * TODO: Do we need?
     *
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
        $iterator = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,
            FilesystemIterator::SKIP_DOTS)), $pattern);
        $offset = strlen($ltrim);
        $files = [];

        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), $offset);
        }

        return $files;
    }



    /**
     * TODO: Do we need?
     *
     * @param $a
     * @param $b
     *
     * @return mixed
     */
    protected function sortAssetsByPriorityThenOrder($a, $b)
    {
        if ($a['priority'] == $b['priority']) {
            return $a['order'] - $b['order'];
        }

        return $b['priority'] - $a['priority'];
    }

}
