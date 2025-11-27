<?php

/**
 * @package    Grav\Common\Assets
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Common\Assets\Traits\AssetUtilsTrait;
use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Object\PropertyObject;
use tubalmartin\CssMin\Minifier as CSSMinifier;
use JShrink\Minifier as JSMinifier;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function array_key_exists;

/**
 * Class Pipeline
 * @package Grav\Common\Assets
 */
class Pipeline extends PropertyObject
{
    use AssetUtilsTrait;

    protected const CSS_ASSET = 1;
    protected const JS_ASSET = 2;
    protected const JS_MODULE_ASSET = 3;

    /** @const Regex to match CSS urls */
    protected const CSS_URL_REGEX = '{url\(([\'\"]?)(.*?)\1\)|(@import)\s+([\'\"])(.*?)\4}';

    /** @const Regex to match JS imports */
    protected const JS_IMPORT_REGEX = '{import.+from\s?[\'|\"](.+?)[\'|\"]}';

    /** @const Regex to match CSS sourcemap comments */
    protected const CSS_SOURCEMAP_REGEX = '{\/\*# (.*?) \*\/}';

    protected const FIRST_FORWARDSLASH_REGEX = '{^\/{1}\w}';

    // Following variables come from the configuration:
    /** @var bool */
    protected $css_minify = false;
    /** @var bool */
    protected $css_minify_windows = false;
    /** @var bool */
    protected $css_rewrite = false;
    /** @var bool */
    protected $css_pipeline_include_externals = true;
    /** @var bool */
    protected $js_minify = false;
    /** @var bool */
    protected $js_minify_windows = false;
    /** @var bool */
    protected $js_pipeline_include_externals = true;

    /** @var string */
    protected $assets_dir;
    /** @var string */
    protected $assets_url;
    /** @var string */
    protected $timestamp;
    /** @var array */
    protected $attributes;
    /** @var string */
    protected $query = '';
    /** @var string */
    protected $asset;

    /**
     * Pipeline constructor.
     * @param array $elements
     * @param string|null $key
     */
    public function __construct(array $elements = [], ?string $key = null)
    {
        parent::__construct($elements, $key);

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        /** @var Config $config */
        $config = Grav::instance()['config'];

        /** @var Uri $uri */
        $uri = Grav::instance()['uri'];

        $this->base_url = rtrim($uri->rootUrl($config->get('system.absolute_urls')), '/') . '/';
        $this->assets_dir = $locator->findResource('asset://');
        if (!$this->assets_dir) {
            // Attempt to create assets folder if it doesn't exist yet.
            $this->assets_dir = $locator->findResource('asset://', true, true);
            Folder::mkdir($this->assets_dir);
            $locator->clearCache();
        }

        $this->assets_url = $locator->findResource('asset://', false);
    }

    /**
     * Minify and concatenate CSS
     *
     * @param array $assets
     * @param string $group
     * @param array $attributes
     * @return bool|string     URL or generated content if available, else false
     */
    public function renderCss($assets, $group, $attributes = [])
    {
        // temporary list of assets to pipeline
        $inline_group = false;

        if (array_key_exists('loading', $attributes) && $attributes['loading'] === 'inline') {
            $inline_group = true;
            unset($attributes['loading']);
        }

        // Store Attributes
        $this->attributes = array_merge(['type' => 'text/css', 'rel' => 'stylesheet'], $attributes);

        // Compute uid based on assets and timestamp
        $json_assets = json_encode($assets);
        $uid = md5($json_assets . (int)$this->css_minify . (int)$this->css_rewrite . $group);
        $file = $uid . '.css';
        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";

        $filepath = "{$this->assets_dir}/{$file}";
        if (file_exists($filepath)) {
            $buffer = file_get_contents($filepath) . "\n";
        } else {
            //if nothing found get out of here!
            if (empty($assets)) {
                return false;
            }

            // Concatenate files
            $buffer = $this->gatherLinks($assets, self::CSS_ASSET);

            // Minify if required
            if ($this->shouldMinify('css')) {
                $minifier = new CSSMinifier();
                $buffer = $minifier->run($buffer);
            }

            // Write file
            if (trim($buffer) !== '') {
                file_put_contents($filepath, $buffer);
            }
        }

        if ($inline_group) {
            $output = "<style>\n" . $buffer . "\n</style>\n";
        } else {
            $this->asset = $relative_path;
            $output = '<link href="' . $relative_path . $this->renderQueryString() . '"' . $this->renderAttributes() . BaseAsset::integrityHash($this->asset) . ">\n";
        }

        return $output;
    }

    /**
     * Minify and concatenate JS files.
     *
     * @param array $assets
     * @param string $group
     * @param array $attributes
     * @return bool|string     URL or generated content if available, else false
     */
    public function renderJs($assets, $group, $attributes = [], $type = self::JS_ASSET)
    {
        // temporary list of assets to pipeline
        $inline_group = false;

        if (array_key_exists('loading', $attributes) && $attributes['loading'] === 'inline') {
            $inline_group = true;
            unset($attributes['loading']);
        }

        // Store Attributes
        $this->attributes = $attributes;

        // Compute uid based on assets and timestamp
        $json_assets = json_encode($assets);
        $uid = md5($json_assets . $this->js_minify . $group);
        $file = $uid . '.js';
        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";

        $filepath = "{$this->assets_dir}/{$file}";
        if (file_exists($filepath)) {
            $buffer = file_get_contents($filepath) . "\n";
        } else {
            //if nothing found get out of here!
            if (empty($assets)) {
                return false;
            }

            // Concatenate files
            $buffer = $this->gatherLinks($assets, $type);

            // Minify if required
            if ($this->shouldMinify('js')) {
                $buffer = $this->minifyJs($buffer, $assets);
            }

            // Write file
            if (trim($buffer) !== '') {
                file_put_contents($filepath, $buffer);
            }
        }

        if ($inline_group) {
            $output = '<script' . $this->renderAttributes(). ">\n" . $buffer . "\n</script>\n";
        } else {
            $this->asset = $relative_path;
            $output = '<script src="' . $relative_path . $this->renderQueryString() . '"' . $this->renderAttributes() . BaseAsset::integrityHash($this->asset) . "></script>\n";
        }

        return $output;
    }

        /**
     * Minify and concatenate JS files.
     *
     * @param array $assets
     * @param string $group
     * @param array $attributes
     * @return bool|string     URL or generated content if available, else false
     */
    public function renderJs_Module($assets, $group, $attributes = [])
    {
        $attributes['type'] = 'module';
        return $this->renderJs($assets, $group, $attributes, self::JS_MODULE_ASSET);
    }

    /**
     * Finds relative CSS urls() and rewrites the URL with an absolute one
     *
     * @param string $file the css source file
     * @param string $dir , $local relative path to the css file
     * @param bool $local is this a local or remote asset
     * @return string
     */
    protected function cssRewrite($file, $dir, $local)
    {
        // Strip any sourcemap comments
        $file = preg_replace(self::CSS_SOURCEMAP_REGEX, '', $file);

        // Find any css url() elements, grab the URLs and calculate an absolute path
        // Then replace the old url with the new one
        $file = (string)preg_replace_callback(self::CSS_URL_REGEX, function ($matches) use ($dir, $local) {
            $isImport = count($matches) > 3 && $matches[3] === '@import';

            if ($isImport) {
                $old_url = $matches[5];
            } else {
                $old_url = $matches[2];
            }
 
            // Ensure link is not rooted to web server, a data URL, or to a remote host
            if (preg_match(self::FIRST_FORWARDSLASH_REGEX, $old_url) || Utils::startsWith($old_url, 'data:') || $this->isRemoteLink($old_url)) {
                return $matches[0];
            }

            // clean leading /
            $old_url = Utils::normalizePath($dir . '/' . $old_url);
            if (preg_match(self::FIRST_FORWARDSLASH_REGEX, $old_url)) {
                $old_url = ltrim($old_url, '/');
            }

            $new_url = ($local ? $this->base_url : '') . $old_url;

            if ($isImport) {
                return str_replace($matches[5], $new_url, $matches[0]);
            } else {
                return str_replace($matches[2], $new_url, $matches[0]);
            }
        }, (string) $file);

        return $file;
    }

    /**
     * Finds relative JS urls() and rewrites the URL with an absolute one
     *
     * @param string $file the css source file
     * @param string $dir local relative path to the css file
     * @param bool $local is this a local or remote asset
     * @return string
     */
    protected function jsRewrite($file, $dir, $local)
    {
        // Find any js import elements, grab the URLs and calculate an absolute path
        // Then replace the old url with the new one
        $file = (string)preg_replace_callback(self::JS_IMPORT_REGEX, function ($matches) use ($dir, $local) {

            $old_url = $matches[1];

            // Ensure link is not rooted to web server, a data URL, or to a remote host
            if (preg_match(self::FIRST_FORWARDSLASH_REGEX, $old_url) || $this->isRemoteLink($old_url)) {
                return $matches[0];
            }

            // clean leading /
            $old_url = Utils::normalizePath($dir . '/' . $old_url);
            $old_url = str_replace('/./', '/', $old_url);
            if (preg_match(self::FIRST_FORWARDSLASH_REGEX, $old_url)) {
                $old_url = ltrim($old_url, '/');
            }

            $new_url = ($local ? $this->base_url : '') . $old_url;

            return str_replace($matches[1], $new_url, $matches[0]);
        }, $file);

        return $file;
    }

    /**
     * @param string $type
     * @return bool
     */
    private function shouldMinify($type = 'css')
    {
        $check = $type . '_minify';
        $win_check = $type . '_minify_windows';

        $minify = (bool) $this->$check;

        // If this is a Windows server, and minify_windows is false (default value) skip the
        // minification process because it will cause Apache to die/crash due to insufficient
        // ThreadStackSize in httpd.conf - See: https://bugs.php.net/bug.php?id=47689
        if (stripos(php_uname('s'), 'WIN') === 0 && !$this->{$win_check}) {
            $minify = false;
        }

        return $minify;
    }

    /**
     * Minify JavaScript with error handling to identify problematic files
     *
     * @param string $buffer Combined JS content
     * @param array $assets Array of asset objects for error reporting
     * @return string Minified JS or original buffer on failure
     * @throws RuntimeException When minification fails and debug mode is enabled
     */
    private function minifyJs(string $buffer, array $assets): string
    {
        try {
            return JSMinifier::minify($buffer);
        } catch (\Exception $e) {
            // Try to identify the problematic file by minifying each asset individually
            $problematicFiles = $this->findProblematicJsAssets($assets);
            $assetList = array_map(fn($asset) => $asset->getAsset(), $assets);

            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];

            $message = "JS Minification failed: {$e->getMessage()}";
            if (!empty($problematicFiles)) {
                $message .= "\nProblematic file(s): " . implode(', ', $problematicFiles);
            }
            $message .= "\nAll files in pipeline: " . implode(', ', $assetList);

            $debugger->addMessage($message, 'error');

            // In debug mode, throw to help developers identify the issue
            if (Grav::instance()['config']->get('system.debugger.enabled', false)) {
                throw new RuntimeException($message, 0, $e);
            }

            // In production, return unminified buffer and log the error
            Grav::instance()['log']->error($message);
            return $buffer;
        }
    }

    /**
     * Find which JS assets fail minification by testing each one individually
     *
     * @param array $assets Array of asset objects
     * @return array List of problematic asset paths
     */
    private function findProblematicJsAssets(array $assets): array
    {
        $problematic = [];

        foreach ($assets as $asset) {
            $link = $asset->getAsset();

            // Get the file content
            if (static::isRemoteLink($link)) {
                if (str_starts_with((string) $link, '//')) {
                    $link = 'http:' . $link;
                }
            } else {
                if (($this->base_url !== '/') && Utils::startsWith($link, $this->base_url)) {
                    $base_url = '#' . preg_quote($this->base_url, '#') . '#';
                    $link = ltrim((string) preg_replace($base_url, '/', $link, 1), '/');
                }
                $link = GRAV_ROOT . '/' . $link;
            }

            $content = @file_get_contents($link);
            if ($content === false) {
                continue;
            }

            try {
                JSMinifier::minify($content);
            } catch (\Exception $e) {
                $problematic[] = $asset->getAsset() . ' (' . $e->getMessage() . ')';
            }
        }

        return $problematic;
    }
}
