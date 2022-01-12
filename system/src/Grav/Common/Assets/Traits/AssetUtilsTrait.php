<?php

/**
 * @package    Grav\Common\Assets\Traits
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets\Traits;

use Closure;
use Grav\Common\Grav;
use Grav\Common\Utils;
use function dirname;
use function in_array;
use function is_array;

/**
 * Trait AssetUtilsTrait
 * @package Grav\Common\Assets\Traits
 */
trait AssetUtilsTrait
{
    /**
     * @var Closure|null
     *
     * Closure used by the pipeline to fetch assets.
     *
     * Useful when file_get_contents() function is not available in your PHP
     * installation or when you want to apply any kind of preprocessing to
     * your assets before they get pipelined.
     *
     * The closure will receive as the only parameter a string with the path/URL of the asset and
     * it should return the content of the asset file as a string.
     */
    protected $fetch_command;

    /** @var string */
    protected $base_url;

    /**
     * Determine whether a link is local or remote.
     * Understands both "http://" and "https://" as well as protocol agnostic links "//"
     *
     * @param  string $link
     * @return bool
     */
    public static function isRemoteLink($link)
    {
        $base = Grav::instance()['uri']->rootUrl(true);

        // Sanity check for local URLs with absolute URL's enabled
        if (Utils::startsWith($link, $base)) {
            return false;
        }

        return (0 === strpos($link, 'http://') || 0 === strpos($link, 'https://') || 0 === strpos($link, '//'));
    }

    /**
     * Download and concatenate the content of several links.
     *
     * @param  array $assets
     * @param  int $type
     * @return string
     */
    protected function gatherLinks(array $assets, int $type = self::CSS_ASSET): string
    {
        $buffer = '';
        foreach ($assets as $asset) {
            $local = true;

            $link = $asset->getAsset();
            $relative_path = $link;

            if (static::isRemoteLink($link)) {
                $local = false;
                if (0 === strpos($link, '//')) {
                    $link = 'http:' . $link;
                }
                $relative_dir = dirname($relative_path);
            } else {
                // Fix to remove relative dir if grav is in one
                if (($this->base_url !== '/') && Utils::startsWith($relative_path, $this->base_url)) {
                    $base_url = '#' . preg_quote($this->base_url, '#') . '#';
                    $relative_path = ltrim(preg_replace($base_url, '/', $link, 1), '/');
                }

                $relative_dir = dirname($relative_path);
                $link = GRAV_ROOT . '/' . $relative_path;
            }

            // TODO: looks like this is not being used.
            $file = $this->fetch_command instanceof Closure ? @$this->fetch_command->__invoke($link) : @file_get_contents($link);

            // No file found, skip it...
            if ($file === false) {
                continue;
            }

            // Double check last character being
            if ($type === self::JS_ASSET || $type === self::JS_MODULE_ASSET) {
                $file = rtrim($file, ' ;') . ';';
            }

            // If this is CSS + the file is local + rewrite enabled
            if ($type === self::CSS_ASSET && $this->css_rewrite) {
                $file = $this->cssRewrite($file, $relative_dir, $local);
            }

            if ($type === self::JS_MODULE_ASSET) {
                $file = $this->jsRewrite($file, $relative_dir, $local);
            }

            $file = rtrim($file) . PHP_EOL;
            $buffer .= $file;
        }

        // Pull out @imports and move to top
        if ($type === self::CSS_ASSET) {
            $buffer = $this->moveImports($buffer);
        }

        return $buffer;
    }

    /**
     * Moves @import statements to the top of the file per the CSS specification
     *
     * @param  string $file the file containing the combined CSS files
     * @return string       the modified file with any @imports at the top of the file
     */
    protected function moveImports($file)
    {
        $regex = '{@import.*?["\']([^"\']+)["\'].*?;}';

        $imports = [];

        $file = (string)preg_replace_callback($regex, static function ($matches) use (&$imports) {
            $imports[] = $matches[0];

            return '';
        }, $file);

        return implode("\n", $imports) . "\n\n" . $file;
    }

    /**
     *
     * Build an HTML attribute string from an array.
     *
     * @return string
     */
    protected function renderAttributes()
    {
        $html = '';
        $no_key = ['loading'];

        foreach ($this->attributes as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_numeric($key)) {
                $key = $value;
            }
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            if (in_array($key, $no_key, true)) {
                $element = htmlentities($value, ENT_QUOTES, 'UTF-8', false);
            } else {
                $element = $key . '="' . htmlentities($value, ENT_QUOTES, 'UTF-8', false) . '"';
            }

            $html .= ' ' . $element;
        }

        return $html;
    }

    /**
     * Render Querystring
     *
     * @param string|null $asset
     * @return string
     */
    protected function renderQueryString($asset = null)
    {
        $querystring = '';

        $asset = $asset ?? $this->asset;

        if (!empty($this->query)) {
            if (Utils::contains($asset, '?')) {
                $querystring .=  '&' . $this->query;
            } else {
                $querystring .= '?' . $this->query;
            }
        }

        if ($this->timestamp) {
            if ($querystring || Utils::contains($asset, '?')) {
                $querystring .=  '&' . $this->timestamp;
            } else {
                $querystring .= '?' . $this->timestamp;
            }
        }

        return $querystring;
    }
}
