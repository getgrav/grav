<?php

/**
 * @package    Grav\Common\Assets
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
use Wikimedia\Minify\CSSMin;
use JShrink\Minifier as JSMinifier;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
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

    /**
     * @const Regex matching, in order, a quoted CSS string, a bang-prefixed license comment, or
     *        an ordinary comment. Strings come first so that comment markers inside a string are
     *        never taken for a comment, and a quote inside a comment is never taken for a string.
     */
    protected const CSS_STRING_OR_COMMENT_REGEX = '~("(?:[^"\\\\\r\n]|\\\\(?:\r\n|.))*"|\'(?:[^\'\\\\\r\n]|\\\\(?:\r\n|.))*\')|(/\*!.*?\*/)|/\*.*?\*/~s';

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
     * @return string|false  Returns the rendered output, or false if no assets
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

        $shouldMinify = $this->shouldMinify('css');

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

            if ($shouldMinify) {
                $result = $this->gatherAndMinifyCss($assets);

                if (empty($result['failed'])) {
                    $buffer = $result['buffer'];
                } else {
                    // Bundling the assets that minified and rendering the failed
                    // ones individually afterward would reorder them relative to
                    // assets that succeeded but come later in $assets, which can
                    // change which rule wins the CSS cascade. Fall back to the
                    // whole group concatenated unminified, in its original order
                    // instead - the same output css_minify: false produces for
                    // these files - so the bundle still caches as one file.
                    $buffer = $this->gatherLinks($assets, self::CSS_ASSET);
                }
            } else {
                $buffer = $this->gatherLinks($assets, self::CSS_ASSET);
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
     * @param int $type
     * @return string|false
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

        //if nothing found get out of here!
        if (empty($assets)) {
            return false;
        }

        $shouldMinify = $this->shouldMinify('js');

        // Compute uid based on all assets. A minify failure now falls back to
        // the whole group rather than a partial bundle plus the failed assets
        // rendered separately, so there is no "successful subset" to key on.
        $json_assets = json_encode($assets);
        $uid = md5($json_assets . (int)$shouldMinify . $group);
        $file = $uid . '.js';
        $relative_path = "{$this->base_url}{$this->assets_url}/{$file}";
        $filepath = "{$this->assets_dir}/{$file}";

        if (file_exists($filepath)) {
            $buffer = file_get_contents($filepath) . "\n";
        } else {
            if ($shouldMinify) {
                $result = $this->gatherAndMinifyJs($assets, $type);

                if (empty($result['failed'])) {
                    $buffer = $result['buffer'];
                } else {
                    // Bundling the assets that minified and rendering the failed
                    // ones individually afterward would move them after
                    // everything that minified successfully, which changes
                    // execution order - a dependency (e.g. jQuery) could end up
                    // loading after code that expects it. Fall back to the whole
                    // group concatenated unminified, in its original order
                    // instead - the same output js_minify: false produces for
                    // these files - so the bundle still caches as one file.
                    $buffer = $this->gatherLinks($assets, $type);
                }
            } else {
                $buffer = $this->gatherLinks($assets, $type);
            }

            // Write file
            if (trim($buffer) !== '') {
                file_put_contents($filepath, $buffer);
            }
        }

        if (trim($buffer) === '') {
            $output = '';
        } elseif ($inline_group) {
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
     * Minify one CSS file.
     *
     * Wikimedia\Minify\CSSMin::minify() collapses whitespace and drops comments with plain
     * regexes, so it does not know where a string starts or ends. Left to itself it would
     * rewrite `content: "Hello, world"` to `content:"Hello,world"`, and it would empty out a
     * string that happens to contain comment markers. Quoted strings are therefore lifted out
     * first and put back afterwards, which also lets bang-prefixed license comments survive as
     * they did under the previous minifier.
     *
     * Throws when the file can't be minified without changing its meaning, so the caller falls
     * back to the unminified bundle.
     *
     * @param string $css
     * @return string
     * @throws \RuntimeException
     */
    private static function minifyCss(string $css): string
    {
        $preserved = [];

        $stripped = preg_replace_callback(
            self::CSS_STRING_OR_COMMENT_REGEX,
            static function (array $matches) use (&$preserved): string {
                $token = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '');
                if ($token === '') {
                    // An ordinary comment: drop it.
                    return '';
                }

                $key = "\x01GRAVCSS" . count($preserved) . "\x01";
                $preserved[$key] = $token;

                return $key;
            },
            $css
        );

        // preg_replace_callback() returns null on a PCRE failure (backtrack limit and the like).
        if ($stripped === null) {
            throw new \RuntimeException('Could not scan the stylesheet: ' . preg_last_error_msg());
        }

        // Every well-formed string is lifted out by now, so a quote left over opens a string
        // that never closes. Browsers end such a string at the line break; once minifying joins
        // the lines it would swallow every rule after it instead.
        if (strpbrk($stripped, '"\'') !== false) {
            throw new \RuntimeException('Unterminated string');
        }

        $stripped = CSSMin::minify($stripped);
        if (preg_last_error() !== PREG_NO_ERROR) {
            throw new \RuntimeException('Could not minify the stylesheet: ' . preg_last_error_msg());
        }

        return $preserved === [] ? $stripped : strtr($stripped, $preserved);
    }

    /**
     * Gather CSS files and minify each one individually.
     * Files that fail minification are tracked and returned separately.
     *
     * @param array $assets Array of asset objects
     * @return array{buffer: string, failed: array} Combined minified content and failed assets
     */
    private function gatherAndMinifyCss(array $assets): array
    {
        $buffer = '';
        $failed = [];

        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        foreach ($assets as $key => $asset) {
            $local = true;
            $link = $asset->getAsset();
            $relative_path = $link;

            if (static::isRemoteLink($link)) {
                $local = false;
                if (str_starts_with((string) $link, '//')) {
                    $link = 'http:' . $link;
                }
                $relative_dir = dirname((string) $relative_path);
            } else {
                // Fix to remove relative dir if grav is in one
                if (($this->base_url !== '/') && Utils::startsWith($relative_path, $this->base_url)) {
                    $base_url = '#' . preg_quote($this->base_url, '#') . '#';
                    $relative_path = ltrim((string) preg_replace($base_url, '/', (string) $link, 1), '/');
                }

                $relative_dir = dirname((string) $relative_path);
                $link = GRAV_ROOT . '/' . $relative_path;
            }

            $file = $this->fetch_command instanceof \Closure ? @$this->fetch_command->__invoke($link) : @file_get_contents($link);

            // No file found, skip it...
            if ($file === false) {
                continue;
            }

            if ($this->css_rewrite) {
                $file = $this->cssRewrite($file, $relative_dir, $local);
            }

            try {
                $file = self::minifyCss($file) . PHP_EOL;
                $buffer .= $file;
            } catch (\Throwable $e) {
                $failed[$key] = $asset;

                $message = "CSS Minification failed for '{$asset->getAsset()}': {$e->getMessage()}";
                $debugger->addMessage($message, 'error');
                Grav::instance()['log']->error($message);
            }
        }

        // moveImports() always prefixes its result with "\n\n" even when there
        // were no @import statements to hoist. The original single-pass design
        // ran the whole buffer through the minifier afterward, which collapsed
        // that filler away; per-asset minification happens before this point
        // now, so strip it here instead.
        $buffer = ltrim($this->moveImports($buffer));

        return ['buffer' => $buffer, 'failed' => $failed];
    }

    /**
     * Gather JS files and minify each one individually.
     * Files that fail minification are tracked and returned separately.
     *
     * @param array $assets Array of asset objects
     * @param int $type Asset type (JS_ASSET or JS_MODULE_ASSET)
     * @return array{buffer: string, failed: array} Combined minified content and failed assets
     */
    private function gatherAndMinifyJs(array $assets, int $type): array
    {
        $buffer = '';
        $failed = [];

        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        foreach ($assets as $key => $asset) {
            $local = true;
            $link = $asset->getAsset();
            $relative_path = $link;

            if (static::isRemoteLink($link)) {
                $local = false;
                if (str_starts_with((string) $link, '//')) {
                    $link = 'http:' . $link;
                }
                $relative_dir = dirname((string) $relative_path);
            } else {
                // Fix to remove relative dir if grav is in one
                if (($this->base_url !== '/') && Utils::startsWith($relative_path, $this->base_url)) {
                    $base_url = '#' . preg_quote($this->base_url, '#') . '#';
                    $relative_path = ltrim((string) preg_replace($base_url, '/', (string) $link, 1), '/');
                }

                $relative_dir = dirname((string) $relative_path);
                $link = GRAV_ROOT . '/' . $relative_path;
            }

            $file = $this->fetch_command instanceof \Closure ? @$this->fetch_command->__invoke($link) : @file_get_contents($link);

            // No file found, skip it...
            if ($file === false) {
                continue;
            }

            // Ensure proper termination
            $file = rtrim((string) $file, ' ;') . ';';

            // Rewrite imports for JS modules
            if ($type === self::JS_MODULE_ASSET) {
                $file = $this->jsRewrite($file, $relative_dir, $local);
            }

            // Try to minify this individual file
            try {
                $file = JSMinifier::minify($file);
                $file = rtrim($file) . PHP_EOL;
                $buffer .= $file;
            } catch (\Throwable $e) {
                // Track the failure so renderJs() can fall back to bundling the
                // whole group unminified, in original order, instead of using
                // this partial per-asset buffer.
                $failed[$key] = $asset;

                $message = "JS Minification failed for '{$asset->getAsset()}': {$e->getMessage()}";
                $debugger->addMessage($message, 'error');
                Grav::instance()['log']->error($message);
            }
        }

        return ['buffer' => $buffer, 'failed' => $failed];
    }
}
