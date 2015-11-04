<?php
namespace Grav\Common;

use Closure;
use Exception;
use FilesystemIterator;
use Grav\Common\Config\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

define('CSS_ASSET', true);
define('JS_ASSET', false);

/**
 * Handles Asset management (CSS & JS) and also pipelining (combining into a single file for each asset)
 *
 * Based on stolz/assets (https://github.com/Stolz/Assets) package modified for use with Grav
 *
 * @author  RocketTheme
 * @license MIT
 */
class Assets
{
    use GravTrait;

    /** @const Regex to match CSS and JavaScript files */
    const DEFAULT_REGEX = '/.\.(css|js)$/i';

    /** @const Regex to match CSS files */
    const CSS_REGEX = '/.\.css$/i';

    /** @const Regex to match JavaScript files */
    const JS_REGEX = '/.\.js$/i';

    /** @const Regex to match CSS urls */
    const CSS_URL_REGEX = '{url\([\'\"]?((?!http|//).*?)[\'\"]?\)}';

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
    protected $js_pipeline = false;

    // The asset holding arrays
    protected $collections = array();
    protected $css = array();
    protected $js = array();
    protected $inline_css = array();
    protected $inline_js = array();

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
    protected $css_no_pipeline = array();
    protected $js_no_pipeline = array();

    public function __construct(array $options = array())
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

        if (isset($config['js_pipeline'])) {
            $this->js_pipeline = $config['js_pipeline'];
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
            $this->timestamp = '?' . self::getGrav()['cache']->getKey();
        }


        return $this;
    }

    /**
     * Initialization called in the Grav lifecycle to initialize the Assets with appropriate configuration
     */
    public function init()
    {
        /** @var Config $config */
        $config = self::getGrav()['config'];
        $base_url = self::getGrav()['base_url'];
        $asset_config = (array)$config->get('system.assets');

        /** @var Locator $locator */
        $locator = self::$grav['locator'];
        $this->assets_dir = self::getGrav()['locator']->findResource('asset://') . DS;
        $this->assets_url = self::getGrav()['locator']->findResource('asset://', false);

        $this->config($asset_config);
        $this->base_url = $base_url . '/';

        // Register any preconfigured collections
        foreach ($config->get('system.assets.collections') as $name => $collection) {
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
    public function add($asset, $priority = null, $pipeline = null)
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
     * Add a CSS asset.
     *
     * It checks for duplicates.
     * You may add more than one asset passing an array as argument.
     *
     * @param  mixed $asset
     * @param  int $priority the priority, bigger comes first
     * @param  bool $pipeline false if this should not be pipelined
     * @param null $group
     *
     * @return $this
     */
    public function addCss($asset, $priority = null, $pipeline = null, $group = null)
    {
        if (is_array($asset)) {
            foreach ($asset as $a) {
                $this->addCss($a, $priority, $pipeline, $group);
            }
            return $this;
        } elseif (isset($this->collections[$asset])) {
            $this->add($this->collections[$asset], $priority, $pipeline, $group);
            return $this;
        }

        if (!$this->isRemoteLink($asset)) {
            $asset = $this->buildLocalLink($asset);
        }

        $data = [
            'asset'    => $asset,
            'priority' => intval($priority ?: 10),
            'order'    => count($this->css),
            'pipeline' => $pipeline ?: true,
            'group' => $group ?: 'head'
        ];

        // check for dynamic array and merge with defaults
        $count_args = func_num_args();
        if (func_num_args() == 2) {
            $dynamic_arg = func_get_arg(1);
            if (is_array($dynamic_arg)) {
                $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if ($asset) {
            $this->css[$key] = $data;
        }

        return $this;
    }

    /**
     * Add a JavaScript asset.
     *
     * It checks for duplicates.
     * You may add more than one asset passing an array as argument.
     *
     * @param  mixed $asset
     * @param  int $priority the priority, bigger comes first
     * @param  bool $pipeline false if this should not be pipelined
     * @param  string $loading how the asset is loaded (async/defer)
     * @param  string $group name of the group
     * @return $this
     */
    public function addJs($asset, $priority = null, $pipeline = null, $loading = null, $group = null)
    {
        if (is_array($asset)) {
            foreach ($asset as $a) {
                $this->addJs($a, $priority, $pipeline, $loading, $group);
            }
            return $this;
        } elseif (isset($this->collections[$asset])) {
            $this->add($this->collections[$asset], $priority, $pipeline, $loading, $group);
            return $this;
        }

        if (!$this->isRemoteLink($asset)) {
            $asset = $this->buildLocalLink($asset);
        }

        $data = [
            'asset'    => $asset,
            'priority' => intval($priority ?: 10),
            'order'    => count($this->js),
            'pipeline' => $pipeline ?: true,
            'loading'  => $loading ?: '',
            'group' => $group ?: 'head'
        ];

        // check for dynamic array and merge with defaults
        $count_args = func_num_args();
        if (func_num_args() == 2) {
            $dynamic_arg = func_get_arg(1);
            if (is_array($dynamic_arg)) {
               $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if ($asset) {
            $this->js[$key] = $data;
        }

        return $this;
    }

    /**
     * Convenience wrapper for async loading of JavaScript
     *
     * @param      $asset
     * @param int  $priority
     * @param bool $pipeline
     * @param string $group name of the group
     *
     * @deprecated Please use dynamic method with ['loading' => 'async']
     *
     * @return \Grav\Common\Assets
     */
    public function addAsyncJs($asset, $priority = null, $pipeline = null, $group = null)
    {
        return $this->addJs($asset, $priority, $pipeline, 'async', $group);
    }

    /**
     * Convenience wrapper for deferred loading of JavaScript
     *
     * @param      $asset
     * @param int  $priority
     * @param bool $pipeline
     * @param string $group name of the group
     *
     * @deprecated Please use dynamic method with ['loading' => 'defer']
     *
     * @return \Grav\Common\Assets
     */
    public function addDeferJs($asset, $priority = null, $pipeline = null, $group = null)
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
     * @param  int $priority the priority, bigger comes first
     * @param null $group
     *
     * @return $this
     */
    public function addInlineCss($asset, $priority = null, $group = null)
    {
        $asset = trim($asset);

        if (is_a($asset, 'Twig_Markup')) {
            preg_match(self::HTML_TAG_REGEX, $asset, $matches );
            if (isset($matches[3]))  {
                $asset = $matches[3];
            }
        }

        $data = [
            'priority'  => intval($priority ?: 10),
            'order'     => count($this->inline_css),
            'asset'     => $asset,
            'group'     => $group ?: 'head'
        ];

        // check for dynamic array and merge with defaults
        $count_args = func_num_args();
        if (func_num_args() == 2) {
            $dynamic_arg = func_get_arg(1);
            if (is_array($dynamic_arg)) {
                $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if (is_string($asset) && !array_key_exists($key, $this->inline_css)) {
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
     * @param string $group name of the group
     *
     * @return $this
     */
    public function addInlineJs($asset, $priority = null, $group = null)
    {
        $asset = trim($asset);

        if (is_a($asset, 'Twig_Markup')) {
            preg_match(self::HTML_TAG_REGEX, $asset, $matches );
            if (isset($matches[3]))  {
                $asset = $matches[3];
            }
        }

        $data = [
            'asset'    => $asset,
            'priority' => intval($priority ?: 10),
            'order'    => count($this->js),
            'group' => $group ?: 'head'
        ];

        // check for dynamic array and merge with defaults
        $count_args = func_num_args();
        if (func_num_args() == 2) {
            $dynamic_arg = func_get_arg(1);
            if (is_array($dynamic_arg)) {
                $data = array_merge($data, $dynamic_arg);
            }
        }

        $key = md5($asset);
        if (is_string($asset) && !array_key_exists($key, $this->inline_js)) {
            $this->inline_js[$key] = $data;
        }

        return $this;
    }

    /**
     * Build the CSS link tags.
     *
     * @param  string $group name of the group
     * @param  array $attributes
     *
     * @return string
     */
    public function css($group = 'head', $attributes = [])
    {
        if (!$this->css) {
            return null;
        }

        // Sort array by priorities (larger priority first)
        if (self::getGrav()) {
            usort($this->css, function ($a, $b) {
                if ($a['priority'] == $b['priority']) {
                    return $b['order'] - $a['order'];
                }
                return $a['priority'] - $b['priority'];
            });

            usort($this->inline_css, function ($a, $b) {
                if ($a['priority'] == $b['priority']) {
                    return $b['order'] - $a['order'];
                }
                return $a['priority'] - $b['priority'];
            });
        }
        $this->css = array_reverse($this->css);
        $this->inline_css = array_reverse($this->inline_css);

        $attributes = $this->attributes(array_merge(['type' => 'text/css', 'rel' => 'stylesheet'], $attributes));

        $output = '';
        $inline_css = '';

        if ($this->css_pipeline) {
            $pipeline_result = $this->pipelineCss($group);
            if ($pipeline_result) {
                $output .= '<link href="' . $pipeline_result . '"' . $attributes . ' />' . "\n";
            }
            foreach ($this->css_no_pipeline as $file) {
                if ($group && $file['group'] == $group) {
                    $media = isset($file['media']) ? sprintf(' media="%s"', $file['media']) : '';
                    $output .= '<link href="' . $file['asset'] . $this->timestamp . '"' . $attributes . $media . ' />' . "\n";
                }
            }
        } else {
            foreach ($this->css as $file) {
                if ($group && $file['group'] == $group) {
                    $media = isset($file['media']) ? sprintf(' media="%s"', $file['media']) : '';
                    $output .= '<link href="' . $file['asset'] . $this->timestamp . '"' . $attributes . $media . ' />' . "\n";
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
     * @param  array $attributes
     *
     * @return string
     */
    public function js($group = 'head', $attributes = [])
    {
        if (!$this->js) {
            return null;
        }

        // Sort array by priorities (larger priority first)
        usort($this->js, function ($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return $b['order'] - $a['order'];
            }
            return $a['priority'] - $b['priority'];
        });

        usort($this->inline_js, function ($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return $b['order'] - $a['order'];
            }
            return $a['priority'] - $b['priority'];
        });

        $this->js = array_reverse($this->js);
        $this->inline_js = array_reverse($this->inline_js);

        $attributes = $this->attributes(array_merge(['type' => 'text/javascript'], $attributes));

        $output = '';
        $inline_js = '';

        if ($this->js_pipeline) {
            $pipeline_result = $this->pipelineJs($group);
            if ($pipeline_result) {
                $output .= '<script src="' . $pipeline_result . '"' . $attributes . ' ></script>' . "\n";
            }
            foreach ($this->js_no_pipeline as $file) {
                if ($group && $file['group'] == $group) {
                    $output .= '<script src="' . $file['asset'] . $this->timestamp . '"' . $attributes . ' ' . $file['loading']. '></script>' . "\n";
                }
            }
        } else {
            foreach ($this->js as $file) {
                if ($group && $file['group'] == $group) {
                    $output .= '<script src="' . $file['asset'] . $this->timestamp . '"' . $attributes . ' ' . $file['loading'] . '></script>' . "\n";
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
     * Minify and concatenate CSS.
     *
     * @return string
     */
    protected function pipelineCss($group = 'head')
    {
        /** @var Cache $cache */
        $cache = self::getGrav()['cache'];
        $key = '?' . $cache->getKey();

        // temporary list of assets to pipeline
        $temp_css = [];

        // clear no-pipeline assets lists
        $this->css_no_pipeline = [];

        $file = md5(json_encode($this->css) . $this->css_minify . $this->css_rewrite . $group) . '.css';

        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";
        $absolute_path = $this->assets_dir . $file;

        // If pipeline exist return it
        if (file_exists($absolute_path)) {
            return $relative_path . $key;
        }

        // Remove any non-pipeline files
        foreach ($this->css as $id => $asset) {
            if ($asset['group'] == $group) {
                if (!$asset['pipeline']) {
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
            $min = new \CSSmin();
            $buffer = $min->run($buffer);
        }

        // Write file
        if (strlen(trim($buffer)) > 0) {
            file_put_contents($absolute_path, $buffer);
            return $relative_path . $key;
        } else {
            return false;
        }
    }

    /**
     * Minify and concatenate JS files.
     *
     * @return string
     */
    protected function pipelineJs($group = 'head')
    {
        /** @var Cache $cache */
        $cache = self::getGrav()['cache'];
        $key = '?' . $cache->getKey();

        // temporary list of assets to pipeline
        $temp_js = [];

        // clear no-pipeline assets lists
        $this->js_no_pipeline = [];

        $file = md5(json_encode($this->js) . $this->js_minify . $group) . '.js';

        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";
        $absolute_path = $this->assets_dir . $file;

        // If pipeline exist return it
        if (file_exists($absolute_path)) {
            return $relative_path . $key;
        }

        // Remove any non-pipeline files
        foreach ($this->js as $id => $asset) {
            if ($asset['group'] == $group) {
                if (!$asset['pipeline']) {
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

        // Concatenate files
        $buffer = $this->gatherLinks($temp_js, JS_ASSET);
        if ($this->js_minify) {
            $buffer = \JSMin::minify($buffer);
        }

        // Write file
        if (strlen(trim($buffer)) > 0) {
            file_put_contents($absolute_path, $buffer);
            return $relative_path . $key;
        } else {
            return false;
        }
    }

    /**
     * Return the array of all the registered CSS assets
     *
     * @return array
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Return the array of all the registered JS assets
     *
     * @return array
     */
    public function getJs()
    {
        return $this->js;
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
     * Determines if an asset exists as a collection, CSS or JS reference
     *
     * @param $asset
     *
     * @return bool
     */
    public function exists($asset)
    {
        if (isset($this->collections[$asset]) ||
            isset($this->css[$asset]) ||
            isset($this->js[$asset])) {
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
        $this->js = array();

        return $this;
    }

    /**
     * Reset CSS assets.
     *
     * @return $this
     */
    public function resetCss()
    {
        $this->css = array();

        return $this;
    }

    /**
     * Add all CSS assets within $directory (relative to public dir).
     *
     * @param  string $directory Relative to $this->public_dir
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
     * @param  string $directory Relative to $this->public_dir
     * @param  string $pattern   (regex)
     *
     * @return $this
     * @throws Exception
     */
    public function addDir($directory, $pattern = self::DEFAULT_REGEX)
    {
        // Check if public_dir exists
        if (!is_dir($this->assets_dir)) {
            throw new Exception('Assets: Public dir not found');
        }

        // Get files
        $files = $this->rglob($this->assets_dir . DIRECTORY_SEPARATOR . $directory, $pattern, $this->assets_dir);

        // No luck? Nothing to do
        if (!$files) {
            return $this;
        }

        // Add CSS files
        if ($pattern === self::CSS_REGEX) {
            $this->css = array_unique(array_merge($this->css, $files));
            return $this;
        }

        // Add JavaScript files
        if ($pattern === self::JS_REGEX) {
            $this->js = array_unique(array_merge($this->js, $files));
            return $this;
        }

        // Unknown pattern. We must poll to know the extension :(
        foreach ($files as $asset) {
            $info = pathinfo($asset);
            if (isset($info['extension'])) {
                $ext = strtolower($info['extension']);
                if ($ext === 'css' && !in_array($asset, $this->css)) {
                    $this->css[] = $asset;
                } elseif ($ext === 'js' && !in_array($asset, $this->js)) {
                    $this->js[] = $asset;
                }
            }
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
        return ('http://' === substr($link, 0, 7) || 'https://' === substr($link, 0, 8)
            || '//' === substr($link, 0, 2));
    }

    /**
     * Build local links including grav asset shortcodes
     *
     * @param  string $asset the asset string reference
     *
     * @return string        the final link url to the asset
     */
    protected function buildLocalLink($asset)
    {
        try {
            $asset = self::getGrav()['locator']->findResource($asset, false);
        } catch (\Exception $e) {
        }

        return $asset ? $this->base_url . ltrim($asset, '/') : false;
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

            $element = $key . '="' . htmlentities($value, ENT_QUOTES, 'UTF-8', false) . '"';
            $html .= ' ' . $element;
        }

        return $html;
    }

    /**
     * Download and concatenate the content of several links.
     *
     * @param  array $links
     * @param  bool $css
     *
     * @return string
     */
    protected function gatherLinks(array $links, $css = true)
    {


        $buffer = '';
        $local = true;

        foreach ($links as $asset) {
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
     * @param $file                 the css source file
     * @param $relative_path        relative path to the css file
     *
     * @return mixed
     */
    protected function cssRewrite($file, $relative_path)
    {
        // Strip any sourcemap comments
        $file = preg_replace(self::CSS_SOURCEMAP_REGEX, '', $file);

        // Find any css url() elements, grab the URLs and calculate an absolute path
        // Then replace the old url with the new one
        $file = preg_replace_callback(
            self::CSS_URL_REGEX,
            function ($matches) use ($relative_path) {

                $old_url = $matches[1];

                // ensure this is not a data url
                if (strpos($old_url, 'data:') === 0) {
                    return $matches[0];
                }

                $new_url = $this->base_url . ltrim(Utils::normalizePath($relative_path . '/' . $old_url), '/');

                return str_replace($old_url, $new_url, $matches[0]);
            },
            $file
        );

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
        $this->imports = array();

        $file = preg_replace_callback(
            self::CSS_IMPORT_REGEX,
            function ($matches) {
                $this->imports[] = $matches[0];
                return '';
            },
            $file
        );

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
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS
                )
            ),
            $pattern
        );
        $offset = strlen($ltrim);
        $files = array();

        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), $offset);
        }

        return $files;
    }

    /**
     * Add all JavaScript assets within $directory.
     *
     * @param  string $directory Relative to $this->public_dir
     *
     * @return $this
     */
    public function addDirJs($directory)
    {
        return $this->addDir($directory, self::JS_REGEX);
    }

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
