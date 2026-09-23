<?php

/**
 * @package    Grav\Common\Assets\Traits
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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

        return (str_starts_with($link, 'http://') || str_starts_with($link, 'https://') || str_starts_with($link, '//'));
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

            // TODO: looks like this is not being used.
            $file = $this->fetch_command instanceof Closure ? @$this->fetch_command->__invoke($link) : @file_get_contents($link);

            // No file found, skip it...
            if ($file === false) {
                continue;
            }

            // Double check last character being
            if ($type === self::JS_ASSET || $type === self::JS_MODULE_ASSET) {
                $file = rtrim((string) $file, ' ;') . ';';
            }

            // If this is CSS + the file is local + rewrite enabled
            if ($type === self::CSS_ASSET && $this->css_rewrite) {
                $file = $this->cssRewrite($file, $relative_dir, $local);
            }

            if ($type === self::JS_MODULE_ASSET) {
                $file = $this->jsRewrite($file, $relative_dir, $local);
            }

            $file = rtrim((string) $file) . PHP_EOL;
            $buffer .= $file;
        }

        // Pull out @imports and move to top
        if ($type === self::CSS_ASSET) {
            $buffer = $this->moveImports($buffer);
        }

        return $buffer;
    }

    /**
     * Moves @import statements to the top of the file per the CSS specification, and keeps a
     * single @charset in front of them.
     *
     * The buffer is scanned for strings and comments as well, so an @import written inside a
     * string or a comment is left where it is, and an @import only ever runs to its own `;`.
     * It never reaches across a `{`, a `}` or into the next rule, which matters once the files
     * have been minified onto one line each. Quoted, url() and unquoted url() forms are all
     * understood, with or without a media query, layer() or supports() after them. Only the
     * first @charset is kept, because anywhere but the very start of a stylesheet it's invalid.
     *
     * @param  string $file the file containing the combined CSS files
     * @return string       the modified file with any @charset and @imports at the top of the file
     */
    protected function moveImports($file)
    {
        $string = '"(?:[^"\\\\\r\n]|\\\\(?:\r\n|.))*"|\'(?:[^\'\\\\\r\n]|\\\\(?:\r\n|.))*\'';
        $regex = '~(' . $string . '|/\*.*?\*/)'
            . '|(@charset\s*(?:' . $string . ')\s*;)'
            . '|(@import\s*(?:url\(\s*(?:' . $string . '|[^)"\'\s]*)\s*\)|' . $string . ')(?:' . $string . '|[^;{}"\'])*;)~is';

        $charset = null;
        $imports = [];

        $result = preg_replace_callback($regex, static function ($matches) use (&$charset, &$imports) {
            if (($matches[2] ?? '') !== '') {
                $charset ??= $matches[2];
            } elseif (($matches[3] ?? '') !== '') {
                $imports[] = trim($matches[3]);
            } else {
                // A string or a comment: leave it untouched.
                return $matches[0];
            }

            return '';
        }, (string) $file);

        // On a PCRE failure leave the buffer as it was rather than lose it.
        if ($result === null) {
            return (string) $file;
        }

        $head = $imports;
        if ($charset !== null) {
            array_unshift($head, $charset);
        }

        return implode("\n", $head) . "\n\n" . $result;
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

            // htmlspecialchars() escapes everything relevant for an HTML attribute
            // (& < > " ') without materializing the full entity table the way
            // htmlentities() does; non-ASCII characters stay literal, which renders
            // identically on UTF-8 pages.
            if (in_array($key, $no_key, true)) {
                $element = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
            } else {
                $element = $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false) . '"';
            }

            $html .= ' ' . $element;
        }

        return $html;
    }

    /**
     * Neutralise the attribute-breakout characters in an asset URL.
     *
     * The URL reaches the tag verbatim from init() for any remote asset, so a
     * quote in it would close src="/href=" early and turn everything after into
     * attributes on our own tag. Only the characters that can break out of, or
     * inject into, a double-quoted attribute are escaped — the query-string `&`
     * is deliberately left alone so a normal `?a=1&b=2` URL renders unchanged.
     *
     * @param string $url
     * @return string
     */
    protected function escapeAssetUrl($url)
    {
        return str_replace(
            ['"', "'", '<', '>'],
            ['&quot;', '&#39;', '&lt;', '&gt;'],
            (string) $url
        );
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

        $asset ??= $this->asset;
        $attributes = $this->attributes;

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
