<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Closure;
use Grav\Common\Assets\Pipeline;
use Grav\Common\Assets\Traits\LegacyAssetsTrait;
use Grav\Common\Assets\Traits\TestingAssetsTrait;
use Grav\Common\Config\Config;
use Grav\Framework\Object\PropertyObject;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function call_user_func_array;
use function count;
use function func_get_args;
use function is_array;

/**
 * Class Assets
 * @package Grav\Common
 */
class Assets extends PropertyObject
{
    use TestingAssetsTrait;
    use LegacyAssetsTrait;

    const CSS = 'css';
    const JS = 'js';
    const CSS_COLLECTION = 'assets_css';
    const JS_COLLECTION = 'assets_js';
    const CSS_TYPE = Assets\Css::class;
    const JS_TYPE = Assets\Js::class;
    const INLINE_CSS_TYPE = Assets\InlineCss::class;
    const INLINE_JS_TYPE = Assets\InlineJs::class;

    /** @const Regex to match CSS and JavaScript files */
    const DEFAULT_REGEX = '/.\.(css|js)$/i';

    /** @const Regex to match CSS files */
    const CSS_REGEX = '/.\.css$/i';

    /** @const Regex to match JavaScript files */
    const JS_REGEX = '/.\.js$/i';

    /** @var string */
    protected $assets_dir;
    /** @var string */
    protected $assets_url;

    /** @var array */
    protected $assets_css = [];
    /** @var array */
    protected $assets_js = [];

    // Following variables come from the configuration:
    /** @var bool */
    protected $css_pipeline;
    /** @var bool */
    protected $css_pipeline_include_externals;
    /** @var bool */
    protected $css_pipeline_before_excludes;
    /** @var bool */
    protected $inlinecss_pipeline_include_externals;
    /** @var bool */
    protected $inlinecss_pipeline_before_excludes;
    /** @var bool */
    protected $js_pipeline;
    /** @var bool */
    protected $js_pipeline_include_externals;
    /** @var bool */
    protected $js_pipeline_before_excludes;
    /** @var bool */
    protected $inlinejs_pipeline_include_externals;
    /** @var bool */
    protected $inlinejs_pipeline_before_excludes;
    /** @var array */
    protected $pipeline_options = [];

    /** @var Closure|string */
    protected $fetch_command;
    /** @var string */
    protected $autoload;
    /** @var bool */
    protected $enable_asset_timestamp;
    /** @var array|null */
    protected $collections;
    /** @var string */
    protected $timestamp;
    /** @var array Keeping track for order counts (for sorting) */
    protected $order = [];

    /**
     * Initialization called in the Grav lifecycle to initialize the Assets with appropriate configuration
     *
     * @return void
     */
    public function init()
    {
        $grav = Grav::instance();
        /** @var Config $config */
        $config = $grav['config'];

        $asset_config = (array)$config->get('system.assets');

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $this->assets_dir = $locator->findResource('asset://');
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
     * @param string|string[] $asset
     * @return $this
     */
    public function add($asset)
    {
        $args = func_get_args();

        // More than one asset
        if (is_array($asset)) {
            foreach ($asset as $index => $location) {
                $params = array_slice($args, 1);
                if (is_array($location)) {
                    $params = array_shift($params);
                    if (is_numeric($params)) {
                        $params = [ 'priority' => $params ];
                    }
                    $params = [array_replace_recursive([], $location, $params)];
                    $location = $index;
                }

                $params = array_merge([$location], $params);
                call_user_func_array([$this, 'add'], $params);
            }
        } elseif (isset($this->collections[$asset])) {
            array_shift($args);
            $args = array_merge([$this->collections[$asset]], $args);
            call_user_func_array([$this, 'add'], $args);
        } else {
            // Get extension
            $extension = pathinfo(parse_url($asset, PHP_URL_PATH), PATHINFO_EXTENSION);

            // JavaScript or CSS
            if ($extension !== '') {
                $extension = strtolower($extension);
                if ($extension === 'css') {
                    call_user_func_array([$this, 'addCss'], $args);
                } elseif ($extension === 'js') {
                    call_user_func_array([$this, 'addJs'], $args);
                }
            }
        }

        return $this;
    }

    /**
     * @param string $collection
     * @param string $type
     * @param string|string[] $asset
     * @param array $options
     * @return $this
     */
    protected function addType($collection, $type, $asset, $options)
    {
        if (is_array($asset)) {
            foreach ($asset as $index => $location) {
                $assetOptions = $options;
                if (is_array($location)) {
                    $assetOptions = array_replace_recursive([], $options, $location);
                    $location = $index;
                }
                $this->addType($collection, $type, $location, $assetOptions);
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
                $exclude_type = ($type === $this::JS_TYPE || $type === $this::INLINE_JS_TYPE) ? $this::JS : $this::CSS;
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
        $group = $options['group'] ?? 'head';
        $position = $options['position'] ?? 'pipeline';

        $orderKey = "{$type}|{$group}|{$position}";
        if (!isset($this->order[$orderKey])) {
           $this->order[$orderKey] = 0;
        }

        $options['order'] = $this->order[$orderKey]++;

        // Create asset of correct type
        $asset_object = new $type();

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
        return $this->addType($this::CSS_COLLECTION, $this::CSS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args(), $this::CSS_TYPE));
    }

    /**
     * Add an Inline CSS asset or a collection of assets.
     *
     * @return $this
     */
    public function addInlineCss($asset)
    {
        return $this->addType($this::CSS_COLLECTION, $this::INLINE_CSS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args(), $this::INLINE_CSS_TYPE));
    }

    /**
     * Add a JS asset or a collection of assets.
     *
     * @return $this
     */
    public function addJs($asset)
    {
        return $this->addType($this::JS_COLLECTION, $this::JS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args(), $this::JS_TYPE));
    }

    /**
     * Add an Inline JS asset or a collection of assets.
     *
     * @return $this
     */
    public function addInlineJs($asset)
    {
        return $this->addType($this::JS_COLLECTION, $this::INLINE_JS_TYPE, $asset, $this->unifyLegacyArguments(func_get_args(), $this::INLINE_JS_TYPE));
    }


    /**
     * Add/replace collection.
     *
     * @param string $collectionName
     * @param array  $assets
     * @param bool    $overwrite
     * @return $this
     */
    public function registerCollection($collectionName, array $assets, $overwrite = false)
    {
        if ($overwrite || !isset($this->collections[$collectionName])) {
            $this->collections[$collectionName] = $assets;
        }

        return $this;
    }

    /**
     * @param array $assets
     * @param string $key
     * @param string $value
     * @param bool $sort
     * @return array|false
     */
    protected function filterAssets($assets, $key, $value, $sort = false)
    {
        $results = array_filter($assets, function ($asset) use ($key, $value) {

            if ($key === 'position' && $value === 'pipeline') {
                $type = $asset->getType();

                if ($asset->getRemote() && $this->{strtolower($type) . '_pipeline_include_externals'} === false && $asset['position'] === 'pipeline') {
                    if ($this->{strtolower($type) . '_pipeline_before_excludes'}) {
                        $asset->setPosition('after');
                    } else {
                        $asset->setPosition('before');
                    }
                    return false;
                }
            }

            if ($asset[$key] === $value) {
                return true;
            }
            return false;
        });

        if ($sort && !empty($results)) {
            $results = $this->sortAssets($results);
        }


        return $results;
    }

    /**
     * @param array $assets
     * @return array
     */
    protected function sortAssets($assets)
    {
        uasort($assets, static function ($a, $b) {
            return $b['priority'] <=> $a['priority'] ?: $a['order'] <=> $b['order'];
        });

        return $assets;
    }

    /**
     * @param string $type
     * @param string $group
     * @param array $attributes
     * @return string
     */
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
     * @return string
     */
    public function js($group = 'head', $attributes = [])
    {
        return $this->render('js', $group, $attributes);
    }
}
