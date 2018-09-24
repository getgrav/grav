<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Assets\Pipeline;
use Grav\Common\Assets\Traits\LegacyAssetsTrait;
use Grav\Common\Assets\Traits\TestingAssetsTrait;
use Grav\Common\Config\Config;
use Grav\Framework\Object\PropertyObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Assets extends PropertyObject
{
    use TestingAssetsTrait;
    use LegacyAssetsTrait;

    const CSS_TYPE = 'Css';
    const JS_TYPE = 'Js';
    const INLINE_CSS_TYPE = 'InlineCss';
    const INLINE_JS_TYPE = 'InlineJs';

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

    protected $assets_css = [];
    protected $assets_js = [];

    // Config Options
    protected $css_pipeline;
    protected $css_pipeline_before_excludes;
    protected $js_pipeline;
    protected $js_pipeline_before_excludes;
    protected $pipeline_options = [];


    protected $fetch_command;
    protected $autoload;
    protected $enable_asset_timestamp;
    protected $collections;
    protected $timestamp;


    /**
     * Initialization called in the Grav lifecycle to initialize the Assets with appropriate configuration
     */
    public function init()
    {
        $grav = Grav::instance();
        /** @var Config $config */
        $config = $grav['config'];

        $asset_config = (array)$config->get('system.assets');

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $this->assets_dir = $locator->findResource('asset://') . DS;
        $this->assets_url = $locator->findResource('asset://', false);

        // Add timestamp if it's enabled
        if ($this->enable_asset_timestamp) {
            $this->timestamp = Grav::instance()['cache']->getKey();
        }

        $this->config($asset_config);

        // Register any preconfigured collections
        foreach ((array) $this->collections as $name => $collection) {
            $this->registerCollection($name, (array)$collection);
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
     * Add an asset or a collection of assets.
     *
     * It automatically detects the asset type (JavaScript, CSS or collection).
     * You may add more than one asset passing an array as argument.
     *
     * @param $asset
     * @return $this
     */
    public function add($asset)
    {
        $options = $this->unifyLegacyArguments(func_get_args());

        // More than one asset
        if (is_array($asset)) {
            foreach ($asset as $a) {
                $this->add($a, $options);
            }
        } elseif (isset($this->collections[$asset])) {
            $this->add($this->collections[$asset], $options);
        } else {
            // Get extension
            $extension = pathinfo(parse_url($asset, PHP_URL_PATH), PATHINFO_EXTENSION);

            // JavaScript or CSS
            if (strlen($extension) > 0) {
                $extension = strtolower($extension);
                if ($extension === 'css') {
                    $this->addCss($asset, $options);
                } elseif ($extension === 'js') {
                    $this->addJs($asset, $options);
                }
            }
        }

        return $this;
    }

    protected function addType($group, $type, $asset, $options)
    {
        if (is_array($asset)) {
            foreach ($asset as $a) {
                $this->addType($group, $type, $a, $options);
            }
            return $this;
        } elseif (($type === $this::CSS_TYPE || $type === $this::JS_TYPE) && isset($this->collections[$asset])) {
            $this->addType($group, $type, $this->collections[$asset], $options);
            return $this;
        }

        // Get assets group
        $asset_group = 'assets_' . strtolower($group);

        // If pipeline disabled, set to position if provided, else after
        if (isset($options['pipeline']) && $options['pipeline'] === false) {
            $options['position'] = $options['position'] ?? 'after';
            unset($options['pipeline']);
        }

        // Add timestamp
        $options['timestamp'] = $this->timestamp;

        // Set order
        $options['order'] = count($this->$asset_group);

        // Create asset of correct type
        $asset_class = "\\Grav\\Common\\Assets\\{$type}";
        $asset_object = new $asset_class();
        $this->$asset_group[md5($asset)] = $asset_object->init($asset, $options);

        return $this;

    }

    /**
     * Add a CSS asset or a collection of assets.
     *
     * @return $this
     */
    public function addCss($asset)
    {
        return $this->addType(Assets::CSS_TYPE,Assets::CSS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args()));
    }

    /**
     * Add an Inline CSS asset or a collection of assets.
     *
     * @return $this
     */
    public function addInlineCss($asset)
    {
        return $this->addType(Assets::CSS_TYPE, Assets::INLINE_CSS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args()));
    }

    /**
     * Add a JS asset or a collection of assets.
     *
     * @return $this
     */
    public function addJs($asset)
    {
        return $this->addType(Assets::JS_TYPE, Assets::JS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args()));
    }

    /**
     * Add an Inline JS asset or a collection of assets.
     *
     * @return $this
     */
    public function addInlineJs($asset)
    {
        return $this->addType(Assets::JS_TYPE, Assets::INLINE_JS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args()));
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
        if ($overwrite || !isset($this->collections[$collectionName])) {
            $this->collections[$collectionName] = $assets;
        }

        return $this;
    }

    protected function filterAssets($assets, $key, $value, $sort = false)
    {
        $results = array_filter($assets, function($asset) use ($key, $value) {
            if ($asset[$key] === $value) return true;
            return false;
        });

        if ($sort && !empty($results)) {
            $results = $this->sortAssets($results);
        }


        return $results;
    }

    protected function sortAssets($assets)
    {
        uasort ($assets, function($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return $a['order'] - $b['order'];
            }
            return $b['priority'] - $a['priority'];
        });
        return $assets;
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
        $before_output = '';
        $pipeline_output = '';
        $after_output = '';
        $no_pipeline = [];

        $group_assets = $this->filterAssets($this->assets_css, 'group', $group);
        $pipeline_assets = $this->filterAssets($group_assets, 'position', 'pipeline', true);
        $before_assets = $this->filterAssets($group_assets, 'position', 'before', true);
        $after_assets = $this->filterAssets($group_assets, 'position', 'after', true);

        // Pipeline
        if ($this->css_pipeline) {
            $options = array_merge($this->pipeline_options, ['timestamp' => $this->timestamp]);

            $pipeline = new Pipeline($options);
            $pipeline_output = $pipeline->renderCss($pipeline_assets, $group, $attributes, $no_pipeline);
        } else {
            foreach ($pipeline_assets as $asset) {
                $pipeline_output .= $asset->render();
            }
        }

        // Handle stuff that couldn't be pipelined
        if (!empty($no_pipeline)) {
            if ($this->css_pipeline_before_excludes) {
                $after_assets = array_merge($after_assets, $no_pipeline);
            } else {
                $before_assets = array_merge($before_assets, $no_pipeline);
            }
        }

        // Before Pipeline
        foreach ($before_assets as $asset) {
            $before_output .= $asset->render();
        }

        // After Pipeline
        foreach ($after_assets as $asset) {
            $after_output .= $asset->render();
        }

        return $before_output . $pipeline_output . $after_output;
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
        $output = '';

        $group_assets = $this->filterAssets($this->assets_js, 'group', $group);
        $before_assets = $this->filterAssets($group_assets, 'position', 'before', true);
        $pipeline_assets = $this->filterAssets($group_assets, 'position', 'pipeline', true);
        $after_assets = $this->filterAssets($group_assets, 'position', 'after', true);

        foreach ($before_assets as $asset) {
            $output .= $asset->render();
        }

        foreach ($pipeline_assets as $asset) {
            $output .= $asset->render();
        }

        foreach ($after_assets as $asset) {
            $output .= $asset->render();
        }

        return $output;
    }
}
