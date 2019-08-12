<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
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

    const CSS_COLLECTION = 'assets_css';
    const JS_COLLECTION = 'assets_js';
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

    protected $assets_dir;
    protected $assets_url;

    protected $assets_css = [];
    protected $assets_js = [];

    // Config Options
    protected $css_pipeline;
    protected $css_pipeline_include_externals;
    protected $css_pipeline_before_excludes;
    protected $js_pipeline;
    protected $js_pipeline_include_externals;
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

        // Add timestamp if it's enabled
        if ($this->enable_asset_timestamp) {
            $this->timestamp = Grav::instance()['cache']->getKey();
        }

        return $this;
    }

    /**
     * Add an asset or a collection of assets.
     *
     * It automatically detects the asset type (JavaScript, CSS or collection).
     * You may add more than one asset passing an array as argument.
     *
     * @param array|string $asset
     * @return $this
     */
    public function add($asset)
    {
        $args = \func_get_args();

        // More than one asset
        if (\is_array($asset)) {
            foreach ($asset as $a) {
                array_shift($args);
                $args = array_merge([$a], $args);
                \call_user_func_array([$this, 'add'], $args);
            }
        } elseif (isset($this->collections[$asset])) {
            array_shift($args);
            $args = array_merge([$this->collections[$asset]], $args);
            \call_user_func_array([$this, 'add'], $args);
        } else {
            // Get extension
            $extension = pathinfo(parse_url($asset, PHP_URL_PATH), PATHINFO_EXTENSION);

            // JavaScript or CSS
            if (\strlen($extension) > 0) {
                $extension = strtolower($extension);
                if ($extension === 'css') {
                    \call_user_func_array([$this, 'addCss'], $args);
                } elseif ($extension === 'js') {
                    \call_user_func_array([$this, 'addJs'], $args);
                }
            }
        }

        return $this;
    }

    protected function addType($collection, $type, $asset, $options)
    {
        if (\is_array($asset)) {
            foreach ($asset as $a) {
                $this->addType($collection, $type, $a, $options);
            }
            return $this;
        }

        if (($type === $this::CSS_TYPE || $type === $this::JS_TYPE) && isset($this->collections[$asset])) {
            $this->addType($collection, $type, $this->collections[$asset], $options);
            return $this;
        }

        // If pipeline disabled, set to position if provided, else after
        if (isset($options['pipeline'])) {
            if ($options['pipeline'] === false) {
                $exclude_type = ($type === $this::JS_TYPE || $type === $this::INLINE_JS_TYPE) ? $this::JS_TYPE : $this::CSS_TYPE;
                $excludes = strtolower($exclude_type . '_pipeline_before_excludes');
                if ($this->{$excludes}) {
                    $default = 'after';
                } else {
                    $default = 'before';
                }

                $options['position'] = $options['position'] ?? $default;
            }

            unset($options['pipeline']);
        }

        // Add timestamp
        $options['timestamp'] = $this->timestamp;

        // Set order
        $options['order'] = \count($this->$collection);

        // Create asset of correct type
        $asset_class = "\\Grav\\Common\\Assets\\{$type}";
        $asset_object = new $asset_class();

        // If exists
        if ($asset_object->init($asset, $options)) {
            $this->$collection[md5($asset)] = $asset_object;
        }

        return $this;

    }

    /**
     * Add a CSS asset or a collection of assets.
     *
     * @return $this
     */
    public function addCss($asset)
    {
        return $this->addType(Assets::CSS_COLLECTION,Assets::CSS_TYPE, $asset, $this->unifyLegacyArguments(\func_get_args(), Assets::CSS_TYPE));
    }

    /**
     * Add an Inline CSS asset or a collection of assets.
     *
     * @return $this
     */
    public function addInlineCss($asset)
    {
        return $this->addType(Assets::CSS_COLLECTION, Assets::INLINE_CSS_TYPE, $asset, $this->unifyLegacyArguments(\func_get_args(), Assets::INLINE_CSS_TYPE));
    }

    /**
     * Add a JS asset or a collection of assets.
     *
     * @return $this
     */
    public function addJs($asset)
    {
        return $this->addType(Assets::JS_COLLECTION, Assets::JS_TYPE, $asset, $this->unifyLegacyArguments(\func_get_args(), Assets::JS_TYPE));
    }

    /**
     * Add an Inline JS asset or a collection of assets.
     *
     * @return $this
     */
    public function addInlineJs($asset)
    {
        return $this->addType(Assets::JS_COLLECTION, Assets::INLINE_JS_TYPE, $asset, $this->unifyLegacyArguments(\func_get_args(), Assets::INLINE_JS_TYPE));
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

            if ($key === 'position' && $value === 'pipeline') {

                $type = $asset->getType();

                if ($asset->getRemote() && $this->{$type . '_pipeline_include_externals'} === false && $asset['position'] === 'pipeline' ) {
                    if ($this->{$type . '_pipeline_before_excludes'}) {
                        $asset->setPosition('after');
                    } else {
                        $asset->setPosition('before');
                    }
                    return false;
                }

            }

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

    public function render($type, $group = 'head', $attributes = [])
    {
        $before_output = '';
        $pipeline_output = '';
        $after_output = '';

        $assets = 'assets_' . $type;
        $pipeline_enabled = $type . '_pipeline';
        $render_pipeline = 'render' . ucfirst($type);

        $group_assets = $this->filterAssets($this->$assets, 'group', $group);
        $pipeline_assets = $this->filterAssets($group_assets, 'position', 'pipeline', true);
        $before_assets = $this->filterAssets($group_assets, 'position', 'before', true);
        $after_assets = $this->filterAssets($group_assets, 'position', 'after', true);

        // Pipeline
        if ($this->{$pipeline_enabled}) {
            $options = array_merge($this->pipeline_options, ['timestamp' => $this->timestamp]);

            $pipeline = new Pipeline($options);
            $pipeline_output = $pipeline->$render_pipeline($pipeline_assets, $group, $attributes);
        } else {
            foreach ($pipeline_assets as $asset) {
                $pipeline_output .= $asset->render();
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
     * Build the CSS link tags.
     *
     * @param  string $group name of the group
     * @param  array  $attributes
     *
     * @return string
     */
    public function css($group = 'head', $attributes = [])
    {
        return $this->render('css', $group, $attributes);
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
        return $this->render('js', $group, $attributes);
    }
}
