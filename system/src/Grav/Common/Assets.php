<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Closure;
use Exception;
use FilesystemIterator;
use Grav\Common\Config\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

define('CSS_ASSET', true);
define('JS_ASSET', false);

class Assets
{
    /** @const Regex to match CSS and JavaScript files */
    const DEFAULT_REGEX = '/.\.(css|js)$/i';

    /** @const Regex to match CSS files */
    const CSS_REGEX = '/.\.css$/i';

    /** @const Regex to match JavaScript files */
    const JS_REGEX = '/.\.js$/i';

    /** @const Regex to match CSS urls */
    const CSS_URL_REGEX = '{url\(([\'\"]?)(.*?)\1\)}';

    /** @const Regex to match CSS sourcemap comments */
    const CSS_SOURCEMAP_REGEX = '{\/\*# (.*) \*\/}';

    /** @const Regex to match CSS import content */
    const CSS_IMPORT_REGEX = '{@import(.*);}';

    const HTML_TAG_REGEX = '#(<([A-Z][A-Z0-9]*)>)+(.*)(<\/\2>)#is';


    /**
     * Closure used by the pipeline to fetch assets.
     *
     * Useful when file_get_contents() function is not available in your PHP
     * installation or when you want to apply any kind of preprocessing to
     * your assets before they get pipelined.
     *
     * The closure will receive as the only parameter a string with the path/URL of the asset and
     * it should return the content of the asset file as a string.
     *
     * @var Closure
     */
    protected $fetch_command;

    // Configuration toggles to enable/disable the pipelining feature
    protected $css_pipeline = false;
    protected $css_pipeline_include_externals = true;
    protected $css_pipeline_before_excludes = true;
    protected $js_pipeline = false;
    protected $js_pipeline_include_externals = true;
    protected $js_pipeline_before_excludes = true;

    // The asset holding arrays
    protected $collections = [];
    protected $css = [];
    protected $js = [];
    protected $inline_css = [];
    protected $inline_js = [];
    protected $imports = [];

    // Some configuration variables
    protected $config;
    protected $base_url;
    protected $timestamp = '';
    protected $assets_dir;
    protected $assets_url;

    // Default values for pipeline settings
    protected $css_minify = true;
    protected $css_minify_windows = false;
    protected $css_rewrite = true;
    protected $js_minify = true;

    // Arrays to hold assets that should NOT be pipelined
    protected $css_no_pipeline = [];
    protected $js_no_pipeline = [];

    /**
     * Assets constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        // Forward config options
        if ($options) {
            $this->config((array)$options);
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
     * @throws \Exception
     */
    public function config(array $config)
    {
        // Set pipeline modes
        if (isset($config['css_pipeline'])) {
            $this->css_pipeline = $config['css_pipeline'];
        }

        if (isset($config['css_pipeline_include_externals'])) {
            $this->css_pipeline_include_externals = $config['css_pipeline_include_externals'];
        }

        if (isset($config['css_pipeline_before_excludes'])) {
            $this->css_pipeline_before_excludes = $config['css_pipeline_before_excludes'];
        }

        if (isset($config['js_pipeline'])) {
            $this->js_pipeline = $config['js_pipeline'];
        }

        if (isset($config['js_pipeline_include_externals'])) {
            $this->js_pipeline_include_externals = $config['js_pipeline_include_externals'];
        }

        if (isset($config['js_pipeline_before_excludes'])) {
            $this->js_pipeline_before_excludes = $config['js_pipeline_before_excludes'];
        }

        // Pipeline requires public dir
        if (($this->js_pipeline || $this->css_pipeline) && !is_dir($this->assets_dir)) {
            throw new \Exception('Assets: Public dir not found');
        }

        // Set custom pipeline fetch command
        if (isset($config['fetch_command']) && ($config['fetch_command'] instanceof Closure)) {
            $this->fetch_command = $config['fetch_command'];
        }

        // Set CSS Minify state
        if (isset($config['css_minify'])) {
            $this->css_minify = $config['css_minify'];
        }

        if (isset($config['css_minify_windows'])) {
            $this->css_minify_windows = $config['css_minify_windows'];
        }

        if (isset($config['css_rewrite'])) {
            $this->css_rewrite = $config['css_rewrite'];
        }

        // Set JS Minify state
        if (isset($config['js_minify'])) {
            $this->js_minify = $config['js_minify'];
        }

        // Set collections
        if (isset($config['collections']) && is_array($config['collections'])) {
            $this->collections = $config['collections'];
        }

        // Autoload assets
        if (isset($config['autoload']) && is_array($config['autoload'])) {
            foreach ($config['autoload'] as $asset) {
                $this->add($asset);
            }
        }

        // Set timestamp
        if (isset($config['enable_asset_timestamp']) && $config['enable_asset_timestamp'] === true) {
            $this->timestamp = '?' . Grav::instance()['cache']->getKey();
        }

        return $this;
    }

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
        foreach ($config->get('system.assets.collections', []) as $name => $collection) {
            $this->registerCollection($name, (array)$collection);
        }
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
    public function add($asset, $priority = null, $pipeline = true)
    {
        // More than one asset
        if (is_array($asset)) {
            foreach ($asset as $a) {
                $this->add($a, $priority, $pipeline);
            }
        } elseif (isset($this->collections[$asset])) {
            $this->add($this->collections[$asset], $priority, $pipeline);
        } else {
            // Get extension
            $extension = pathinfo(parse_url($asset, PHP_URL_PATH), PATHINFO_EXTENSION);

            // JavaScript or CSS
            if (strlen($extension) > 0) {
                $extension = strtolower($extension);
                if ($extension === 'css') {
                    $this->addCss($asset, $priority, $pipeline);
                } elseif ($extension === 'js') {
                    $this->addJs($asset, $priority, $pipeline);
                }
            }
        }

        return $this;
    }

    /**
     * Add an asset to its assembly.
     *
     * It checks for duplicates.
     * You may add more than one asset passing an array as argument.
     * The third argument may alternatively contain an array of options which take precedence over positional
     * arguments.
     *
     * @param  array   $assembly the array assembling the assets
     * @param  mixed   $asset
     * @param  int     $priority the priority, bigger comes first
     * @param  bool    $pipeline false if this should not be pipelined
     * @param  string  $loading  how the asset is loaded (async/defer/inline, for CSS: only inline)
     * @param  string  $group    name of the group
     *
     * @return $this
     */
    public function addTo(&$assembly, $asset, $priority = null, $pipeline = true, $loading = null, $group = null)
    {
        if (is_array($asset)) {
            foreach ($asset as $a) {
                $this->addTo($assembly, $a, $priority, $pipeline, $loading, $group);
            }

            return $this;
        } elseif (isset($this->collections[$asset])) {
            $this->addTo($assembly, $this->collections[$asset], $priority, $pipeline, $loading, $group);

            return $this;
        }

        $modified = false;
        $remote = $this->isRemoteLink($asset);
        if (!$remote) {
            $modified = $this->getLastModificationTime($asset);
            $asset = $this->buildLocalLink($asset);
        }

        // Check for existence
        if ($asset === false) {
            return $this;
        }

        $data = [
            'asset'    => $asset,
            'remote'   => $remote,
            'priority' => intval($priority ?: 10),
            'order'    => count($assembly),
            'pipeline' => (bool) $pipeline,
            'loading'  => $loading ?: '',
            'group'    => $group ?: 'head',
            'modified' => $modified
        ];

        // check for dynamic array and merge with defaults
        if (func_num_args() > 2) {
            $dynamic_arg = func_get_arg(2);
            if (is_array($dynamic_arg)) {
                $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if ($asset) {
            $assembly[$key] = $data;
        }

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
    public function addCss($asset, $priority = null, $pipeline = true, $group = null, $loading = null)
    {
        return $this->addTo($this->css, $asset, $priority, $pipeline, $loading, $group);
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
    public function addJs($asset, $priority = null, $pipeline = true, $loading = null, $group = null)
    {
        return $this->addTo($this->js, $asset, $priority, $pipeline, $loading, $group);
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
        return $this->addJs($asset, $priority, $pipeline, 'async', $group);
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
        return $this->addJs($asset, $priority, $pipeline, 'defer', $group);
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
    public function addInlineCss($asset, $priority = null, $group = null)
    {
        $asset = trim($asset);

        if (is_a($asset, 'Twig_Markup')) {
            preg_match(self::HTML_TAG_REGEX, $asset, $matches);
            if (isset($matches[3])) {
                $asset = $matches[3];
            }
        }

        $data = [
            'priority' => intval($priority ?: 10),
            'order'    => count($this->inline_css),
            'asset'    => $asset,
            'group'    => $group ?: 'head'
        ];

        // check for dynamic array and merge with defaults
        if (func_num_args() == 2) {
            $dynamic_arg = func_get_arg(1);
            if (is_array($dynamic_arg)) {
                $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if ($asset && is_string($asset) && !array_key_exists($key, $this->inline_css)) {
            $this->inline_css[$key] = $data;
        }

        return $this;
    }

    /**
     * Add an inline JS asset.
     *
     * It checks for duplicates.
     * For adding chunks of string-based inline JS
     *
     * @param  mixed $asset
     * @param  int   $priority the priority, bigger comes first
     * @param string $group    name of the group
     *
     * @return $this
     */
    public function addInlineJs($asset, $priority = null, $group = null)
    {
        $asset = trim($asset);

        if (is_a($asset, 'Twig_Markup')) {
            preg_match(self::HTML_TAG_REGEX, $asset, $matches);
            if (isset($matches[3])) {
                $asset = $matches[3];
            }
        }

        $data = [
            'asset'    => $asset,
            'priority' => intval($priority ?: 10),
            'order'    => count($this->js),
            'group'    => $group ?: 'head'
        ];

        // check for dynamic array and merge with defaults
        if (func_num_args() == 2) {
            $dynamic_arg = func_get_arg(1);
            if (is_array($dynamic_arg)) {
                $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if ($asset && is_string($asset) && !array_key_exists($key, $this->inline_js)) {
            $this->inline_js[$key] = $data;
        }

        return $this;
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
        if (!$this->css && !$this->inline_css) {
            return null;
        }

        // Sort array by priorities (larger priority first)
        if (Grav::instance()) {
            uasort($this->css, function ($a, $b) {
                if ($a['priority'] == $b['priority']) {
                    return $b['order'] - $a['order'];
                }

                return $a['priority'] - $b['priority'];
            });

            uasort($this->inline_css, function ($a, $b) {
                if ($a['priority'] == $b['priority']) {
                    return $b['order'] - $a['order'];
                }

                return $a['priority'] - $b['priority'];
            });
        }
        $this->css = array_reverse($this->css);
        $this->inline_css = array_reverse($this->inline_css);

        $inlineGroup = array_key_exists('loading', $attributes) && $attributes['loading'] === 'inline';

        $attributes = $this->attributes(array_merge(['type' => 'text/css', 'rel' => 'stylesheet'], $attributes));

        $output = '';
        $inline_css = '';

        if ($this->css_pipeline) {
            $pipeline_result = $this->pipelineCss($group, !$inlineGroup);
            $pipeline_html = ($inlineGroup ? '' : '<link href="' . $pipeline_result . '"' . $attributes . ' />' . "\n");

            if ($this->css_pipeline_before_excludes && $pipeline_result) {
                if ($inlineGroup) {
                    $inline_css .= $pipeline_result;
                }
                else {
                    $output .= $pipeline_html;
                }
            }
            foreach ($this->css_no_pipeline as $file) {
                if ($group && $file['group'] == $group) {
                    if ($file['loading'] === 'inline') {
                        $inline_css .= $this->gatherLinks([$file], CSS_ASSET) . "\n";
                    }
                    else {
                        $media = isset($file['media']) ? sprintf(' media="%s"', $file['media']) : '';
                        $output .= '<link href="' . $file['asset'] . $this->getTimestamp($file) . '"' . $attributes . $media . ' />' . "\n";
                    }
                }
            }
            if (!$this->css_pipeline_before_excludes && $pipeline_result) {
                if ($inlineGroup) {
                    $inline_css .= $pipeline_result;
                }
                else {
                    $output .= $pipeline_html;
                }
            }
        } else {
            foreach ($this->css as $file) {
                if ($group && $file['group'] == $group) {
                    if ($inlineGroup || $file['loading'] === 'inline') {
                        $inline_css .= $this->gatherLinks([$file], CSS_ASSET) . "\n";
                    }
                    else {
                        $media = isset($file['media']) ? sprintf(' media="%s"', $file['media']) : '';
                        $output .= '<link href="' . $file['asset'] . $this->getTimestamp($file) . '"' . $attributes . $media . ' />' . "\n";
                    }
                }
            }
        }

        // Render Inline CSS
        foreach ($this->inline_css as $inline) {
            if ($group && $inline['group'] == $group) {
                $inline_css .= $inline['asset'] . "\n";
            }
        }

        if ($inline_css) {
            $output .= "\n<style>\n" . $inline_css . "\n</style>\n";
        }


        return $output;
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
        if (!$this->js && !$this->inline_js) {
            return null;
        }

        // Sort array by priorities (larger priority first)
        uasort($this->js, function ($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return $b['order'] - $a['order'];
            }

            return $a['priority'] - $b['priority'];
        });

        uasort($this->inline_js, function ($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return $b['order'] - $a['order'];
            }

            return $a['priority'] - $b['priority'];
        });

        $this->js = array_reverse($this->js);
        $this->inline_js = array_reverse($this->inline_js);

        $inlineGroup = array_key_exists('loading', $attributes) && $attributes['loading'] === 'inline';

        $attributes = $this->attributes(array_merge(['type' => 'text/javascript'], $attributes));

        $output = '';
        $inline_js = '';

        if ($this->js_pipeline) {
            $pipeline_result = $this->pipelineJs($group, !$inlineGroup);
            $pipeline_html = ($inlineGroup ? '' : '<script src="' . $pipeline_result . '"' . $attributes . ' ></script>' . "\n");

            if ($this->js_pipeline_before_excludes && $pipeline_result) {
                if ($inlineGroup) {
                    $inline_js .= $pipeline_result;
                }
                else {
                    $output .= $pipeline_html;
                }
            }
            foreach ($this->js_no_pipeline as $file) {
                if ($group && $file['group'] == $group) {
                    if ($file['loading'] === 'inline') {
                        $inline_js .= $this->gatherLinks([$file], JS_ASSET) . "\n";
                    }
                    else {
                        $output .= '<script src="' . $file['asset'] . $this->getTimestamp($file) . '"' . $attributes . ' ' . $file['loading'] . '></script>' . "\n";
                    }
                }
            }
            if (!$this->js_pipeline_before_excludes && $pipeline_result) {
                if ($inlineGroup) {
                    $inline_js .= $pipeline_result;
                }
                else {
                    $output .= $pipeline_html;
                }
            }
        } else {
            foreach ($this->js as $file) {
                if ($group && $file['group'] == $group) {
                    if ($inlineGroup || $file['loading'] === 'inline') {
                        $inline_js .= $this->gatherLinks([$file], JS_ASSET) . "\n";
                    }
                    else {
                        $output .= '<script src="' . $file['asset'] . $this->getTimestamp($file) . '"' . $attributes . ' ' . $file['loading'] . '></script>' . "\n";
                    }
                }
            }
        }

        // Render Inline JS
        foreach ($this->inline_js as $inline) {
            if ($group && $inline['group'] == $group) {
                $inline_js .= $inline['asset'] . "\n";
            }
        }

        if ($inline_js) {
            $output .= "\n<script>\n" . $inline_js . "\n</script>\n";
        }

        return $output;
    }

    /**
     * Minify and concatenate CSS
     *
     * @param string $group
     * @param bool $returnURL  true if pipeline should return the URL, otherwise the content
     *
     * @return bool|string     URL or generated content if available, else false
     */
    protected function pipelineCss($group = 'head', $returnURL = true)
    {
        // temporary list of assets to pipeline
        $temp_css = [];

        // clear no-pipeline assets lists
        $this->css_no_pipeline = [];

        // Compute uid based on assets and timestamp
        $uid = md5(json_encode($this->css) . $this->css_minify . $this->css_rewrite . $group);
        $file =  $uid . '.css';
        $inline_file = $uid . '-inline.css';

        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";

        // If inline files exist set them on object
        if (file_exists($this->assets_dir . $inline_file)) {
            $this->css_no_pipeline = json_decode(file_get_contents($this->assets_dir . $inline_file), true);
        }

        // If pipeline exist return its URL or content
        if (file_exists($this->assets_dir . $file)) {
            if ($returnURL) {
                return $relative_path . $this->getTimestamp();
            }
            else {
                return file_get_contents($this->assets_dir . $file) . "\n";
            }
        }

        // Remove any non-pipeline files
        foreach ($this->css as $id => $asset) {
            if ($asset['group'] == $group) {
                if (!$asset['pipeline'] ||
                    ($asset['remote'] && $this->css_pipeline_include_externals === false)) {
                    $this->css_no_pipeline[$id] = $asset;
                } else {
                    $temp_css[$id] = $asset;
                }
            }
        }

        //if nothing found get out of here!
        if (count($temp_css) == 0) {
            return false;
        }

        // Write non-pipeline files out
        if (!empty($this->css_no_pipeline)) {
            file_put_contents($this->assets_dir . $inline_file, json_encode($this->css_no_pipeline));
        }


        $css_minify = $this->css_minify;

        // If this is a Windows server, and minify_windows is false (default value) skip the
        // minification process because it will cause Apache to die/crash due to insufficient
        // ThreadStackSize in httpd.conf - See: https://bugs.php.net/bug.php?id=47689
        if (strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN' && !$this->css_minify_windows) {
            $css_minify = false;
        }

        // Concatenate files
        $buffer = $this->gatherLinks($temp_css, CSS_ASSET);
        if ($css_minify) {
            $minifier = new \MatthiasMullie\Minify\CSS();
            $minifier->add($buffer);
            $buffer = $minifier->minify();
        }

        // Write file
        if (strlen(trim($buffer)) > 0) {
            file_put_contents($this->assets_dir . $file, $buffer);

            if ($returnURL) {
                return $relative_path . $this->getTimestamp();
            }
            else {
                return $buffer . "\n";
            }
        } else {
            return false;
        }
    }

    /**
     * Minify and concatenate JS files.
     *
     * @param string $group
     * @param bool $returnURL  true if pipeline should return the URL, otherwise the content
     *
     * @return bool|string     URL or generated content if available, else false
     */
    protected function pipelineJs($group = 'head', $returnURL = true)
    {
        // temporary list of assets to pipeline
        $temp_js = [];

        // clear no-pipeline assets lists
        $this->js_no_pipeline = [];

        // Compute uid based on assets and timestamp
        $uid = md5(json_encode($this->js) . $this->js_minify . $group);
        $file =  $uid . '.js';
        $inline_file = $uid . '-inline.js';

        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";

        // If inline files exist set them on object
        if (file_exists($this->assets_dir . $inline_file)) {
            $this->js_no_pipeline = json_decode(file_get_contents($this->assets_dir . $inline_file), true);
        }

        // If pipeline exist return its URL or content
        if (file_exists($this->assets_dir . $file)) {
            if ($returnURL) {
                return $relative_path . $this->getTimestamp();
            }
            else {
                return file_get_contents($this->assets_dir . $file) . "\n";
            }
        }

        // Remove any non-pipeline files
        foreach ($this->js as $id => $asset) {
            if ($asset['group'] == $group) {
                if (!$asset['pipeline'] ||
                    ($asset['remote'] && $this->js_pipeline_include_externals === false)) {
                    $this->js_no_pipeline[] = $asset;
                } else {
                    $temp_js[$id] = $asset;
                }
            }
        }

        //if nothing found get out of here!
        if (count($temp_js) == 0) {
            return false;
        }

        // Write non-pipeline files out
        if (!empty($this->js_no_pipeline)) {
            file_put_contents($this->assets_dir . $inline_file, json_encode($this->js_no_pipeline));
        }

        // Concatenate files
        $buffer = $this->gatherLinks($temp_js, JS_ASSET);
        if ($this->js_minify) {
            $minifier = new \MatthiasMullie\Minify\JS();
            $minifier->add($buffer);
            $buffer = $minifier->minify();
        }

        // Write file
        if (strlen(trim($buffer)) > 0) {
            file_put_contents($this->assets_dir . $file, $buffer);

            if ($returnURL) {
                return $relative_path . $this->getTimestamp();
            }
            else {
                return $buffer . "\n";
            }
        } else {
            return false;
        }
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
        if (!empty($key)) {
            $asset_key = md5($key);
            if (isset($this->css[$asset_key])) {
                return $this->css[$asset_key];
            } else {
                return null;
            }
        }

        return $this->css;
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
        if (!empty($key)) {
            $asset_key = md5($key);
            if (isset($this->js[$asset_key])) {
                return $this->js[$asset_key];
            } else {
                return null;
            }
        }

        return $this->js;
    }

    /**
     * Set the whole array of CSS assets
     *
     * @param $css
     */
    public function setCss($css)
    {
        $this->css = $css;
    }

    /**
     * Set the whole array of JS assets
     *
     * @param $js
     */
    public function setJs($js)
    {
        $this->js = $js;
    }

    /**
     * Removes an item from the CSS array if set
     *
     * @param string $key  The asset key
     */
    public function removeCss($key)
    {
        $asset_key = md5($key);
        if (isset($this->css[$asset_key])) {
            unset($this->css[$asset_key]);
        }
    }

    /**
     * Removes an item from the JS array if set
     *
     * @param string $key  The asset key
     */
    public function removeJs($key)
    {
        $asset_key = md5($key);
        if (isset($this->js[$asset_key])) {
            unset($this->js[$asset_key]);
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
     */
    public function setCollection($collections)
    {
        $this->collections = $collections;
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
        if (isset($this->collections[$asset]) || isset($this->css[$asset]) || isset($this->js[$asset])) {
            return true;
        } else {
            return false;
        }
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

    /**
     * Reset all assets.
     *
     * @return $this
     */
    public function reset()
    {
        return $this->resetCss()->resetJs();
    }

    /**
     * Reset JavaScript assets.
     *
     * @return $this
     */
    public function resetJs()
    {
        $this->js = [];
        $this->inline_js = [];

        return $this;
    }

    /**
     * Reset CSS assets.
     *
     * @return $this
     */
    public function resetCss()
    {
        $this->css = [];
        $this->inline_css = [];

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
     * Download and concatenate the content of several links.
     *
     * @param  array $links
     * @param  bool  $css
     *
     * @return string
     */
    protected function gatherLinks(array $links, $css = true)
    {
        $buffer = '';


        foreach ($links as $asset) {
            $relative_dir = '';
            $local = true;

            $link = $asset['asset'];
            $relative_path = $link;

            if ($this->isRemoteLink($link)) {
                $local = false;
                if ('//' === substr($link, 0, 2)) {
                    $link = 'http:' . $link;
                }
            } else {
                // Fix to remove relative dir if grav is in one
                if (($this->base_url != '/') && (strpos($this->base_url, $link) == 0)) {
                    $base_url = '#' . preg_quote($this->base_url, '#') . '#';
                    $relative_path = ltrim(preg_replace($base_url, '/', $link, 1), '/');
                }

                $relative_dir = dirname($relative_path);
                $link = ROOT_DIR . $relative_path;
            }

            $file = ($this->fetch_command instanceof Closure) ? @$this->fetch_command->__invoke($link) : @file_get_contents($link);

            // No file found, skip it...
            if ($file === false) {
                continue;
            }

            // Double check last character being
            if (!$css) {
                $file = rtrim($file, ' ;') . ';';
            }

            // If this is CSS + the file is local + rewrite enabled
            if ($css && $local && $this->css_rewrite) {
                $file = $this->cssRewrite($file, $relative_dir);
            }

            $file = rtrim($file) . PHP_EOL;
            $buffer .= $file;
        }

        // Pull out @imports and move to top
        if ($css) {
            $buffer = $this->moveImports($buffer);
        }

        return $buffer;
    }

    /**
     * Finds relative CSS urls() and rewrites the URL with an absolute one
     *
     * @param string $file          the css source file
     * @param string $relative_path relative path to the css file
     *
     * @return mixed
     */
    protected function cssRewrite($file, $relative_path)
    {
        // Strip any sourcemap comments
        $file = preg_replace(self::CSS_SOURCEMAP_REGEX, '', $file);

        // Find any css url() elements, grab the URLs and calculate an absolute path
        // Then replace the old url with the new one
        $file = preg_replace_callback(self::CSS_URL_REGEX, function ($matches) use ($relative_path) {

            $old_url = $matches[2];

            // Ensure link is not rooted to webserver, a data URL, or to a remote host
            if (Utils::startsWith($old_url, '/') || Utils::startsWith($old_url, 'data:') || $this->isRemoteLink($old_url)) {
                return $matches[0];
            }

            $new_url = $this->base_url . ltrim(Utils::normalizePath($relative_path . '/' . $old_url), '/');

            return str_replace($old_url, $new_url, $matches[0]);
        }, $file);

        return $file;
    }

    /**
     * Moves @import statements to the top of the file per the CSS specification
     *
     * @param  string $file the file containing the combined CSS files
     *
     * @return string       the modified file with any @imports at the top of the file
     */
    protected function moveImports($file)
    {
        $this->imports = [];

        $file = preg_replace_callback(self::CSS_IMPORT_REGEX, function ($matches) {
            $this->imports[] = $matches[0];

            return '';
        }, $file);

        return implode("\n", $this->imports) . "\n\n" . $file;
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
     * Sets the state of CSS Pipeline
     *
     * @param boolean $value
     */
    public function setCssPipeline($value)
    {
        $this->css_pipeline = (bool)$value;
    }

    /**
     * Sets the state of JS Pipeline
     *
     * @param boolean $value
     */
    public function setJsPipeline($value)
    {
        $this->js_pipeline = (bool)$value;
    }

    /**
     * Explicitly set's a timestamp for assets
     *
     * @param $value
     */
    public function setTimestamp($value)
    {
        $this->timestamp = '?' . $value;
    }

    public function getTimestamp($asset = null)
    {
        if (is_array($asset)) {
            if ($asset['remote'] === false) {
                if (Utils::contains($asset['asset'], '?')) {
                    return str_replace('?', '&', $this->timestamp);
                } else {
                    return $this->timestamp;
                }
            }
        } elseif (empty($asset)) {
            return $this->timestamp;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return '';
    }

    /**
     * @param $a
     * @param $b
     *
     * @return mixed
     */
    protected function priorityCompare($a, $b)
    {
        return $a ['priority'] - $b ['priority'];
    }

}
