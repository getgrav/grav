<?php
/**
 * @package    Grav.Common.Assets
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Framework\Object\ArrayObject;

class Pipeline extends ArrayObject
{

    /** @const Regex to match CSS urls */
    const CSS_URL_REGEX = '{url\(([\'\"]?)(.*?)\1\)}';

    /** @const Regex to match CSS sourcemap comments */
    const CSS_SOURCEMAP_REGEX = '{\/\*# (.*?) \*\/}';

    /** @const Regex to match CSS import content */
    const CSS_IMPORT_REGEX = '{@import(.*?);}';



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
}
