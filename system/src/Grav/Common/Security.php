<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Page\Pages;
use Rhukster\DomSanitizer\DOMSanitizer;
use function chr;
use function count;
use function is_array;
use function is_string;

/**
 * Class Security
 * @package Grav\Common
 */
class Security
{
    /**
     * @param string $filepath
     * @param array|null $options
     * @return string|null
     */
    public static function detectXssFromSvgFile(string $filepath, array $options = null): ?string
    {
        if (file_exists($filepath) && Grav::instance()['config']->get('security.sanitize_svg')) {
            $content = file_get_contents($filepath);

            return static::detectXss($content, $options);
        }

        return null;
    }

    /**
     * Sanitize SVG string for XSS code
     *
     * @param string $svg
     * @return string
     */
    public static function sanitizeSvgString(string $svg): string
    {
        if (Grav::instance()['config']->get('security.sanitize_svg')) {
            $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
            $sanitized = $sanitizer->sanitize($svg);
            if (is_string($sanitized)) {
                $svg = $sanitized;
            }
        }

        return $svg;
    }

    /**
     * Sanitize SVG for XSS code
     *
     * @param string $file
     * @return void
     */
    public static function sanitizeSVG(string $file): void
    {
        if (file_exists($file) && Grav::instance()['config']->get('security.sanitize_svg')) {
            $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
            $original_svg = file_get_contents($file);
            $clean_svg = $sanitizer->sanitize($original_svg);

            // Quarantine bad SVG files and throw exception
            if ($clean_svg !== false ) {
                file_put_contents($file, $clean_svg);
            } else {
                $quarantine_file = Utils::basename($file);
                $quarantine_dir = 'log://quarantine';
                Folder::mkdir($quarantine_dir);
                file_put_contents("$quarantine_dir/$quarantine_file", $original_svg);
                unlink($file);
                throw new Exception('SVG could not be sanitized, it has been moved to the logs/quarantine folder');
            }
        }
    }

    /**
     * Detect XSS code in Grav pages
     *
     * @param Pages $pages
     * @param bool $route
     * @param callable|null $status
     * @return array
     */
    public static function detectXssFromPages(Pages $pages, $route = true, callable $status = null)
    {
        $routes = $pages->getList(null, 0, true);

        // Remove duplicate for homepage
        unset($routes['/']);

        $list = [];

        // This needs Symfony 4.1 to work
        $status && $status([
            'type' => 'count',
            'steps' => count($routes),
        ]);

        foreach (array_keys($routes) as $route) {
            $status && $status([
                'type' => 'progress',
            ]);

            try {
                $page = $pages->find($route);
                if ($page->exists()) {
                    // call the content to load/cache it
                    $header = (array) $page->header();
                    $content = $page->value('content');

                    $data = ['header' => $header, 'content' => $content];
                    $results = static::detectXssFromArray($data);

                    if (!empty($results)) {
                        $list[$page->rawRoute()] = $results;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $list;
    }

    /**
     * Detect XSS in an array or strings such as $_POST or $_GET
     *
     * @param array $array      Array such as $_POST or $_GET
     * @param array|null $options Extra options to be passed.
     * @param string $prefix    Prefix for returned values.
     * @return array            Returns flatten list of potentially dangerous input values, such as 'data.content'.
     */
    public static function detectXssFromArray(array $array, string $prefix = '', array $options = null)
    {
        if (null === $options) {
            $options = static::getXssDefaults();
        }

        $list = [[]];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $list[] = static::detectXssFromArray($value, $prefix . $key . '.', $options);
            }
            if ($result = static::detectXss($value, $options)) {
                $list[] = [$prefix . $key => $result];
            }
        }

        return array_merge(...$list);
    }

    /**
     * Determine if string potentially has a XSS attack. This simple function does not catch all XSS and it is likely to
     *
     * return false positives because of it tags all potentially dangerous HTML tags and attributes without looking into
     * their content.
     *
     * @param string|null $string The string to run XSS detection logic on
     * @param array|null $options
     * @return string|null       Type of XSS vector if the given `$string` may contain XSS, false otherwise.
     *
     * Copies the code from: https://github.com/symphonycms/xssfilter/blob/master/extension.driver.php#L138
     */
    public static function detectXss($string, array $options = null): ?string
    {
        // Skip any null or non string values
        if (null === $string || !is_string($string) || empty($string)) {
            return null;
        }

        if (null === $options) {
            $options = static::getXssDefaults();
        }

        $enabled_rules = (array)($options['enabled_rules'] ?? null);
        $dangerous_tags = (array)($options['dangerous_tags'] ?? null);
        if (!$dangerous_tags) {
            $enabled_rules['dangerous_tags'] = false;
        }
        $invalid_protocols = (array)($options['invalid_protocols'] ?? null);
        if (!$invalid_protocols) {
            $enabled_rules['invalid_protocols'] = false;
        }
        $enabled_rules = array_filter($enabled_rules, static function ($val) { return !empty($val); });
        if (!$enabled_rules) {
            return null;
        }

        // Keep a copy of the original string before cleaning up
        $orig = $string;

        // URL decode
        $string = urldecode($string);

        // Convert Hexadecimals
        $string = (string)preg_replace_callback('!(&#|\\\)[xX]([0-9a-fA-F]+);?!u', static function ($m) {
            return chr(hexdec($m[2]));
        }, $string);

        // Clean up entities
        $string = preg_replace('!(&#[0-9]+);?!u', '$1;', $string);

        // Decode entities
        $string = html_entity_decode($string, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');

        // Strip whitespace characters
        $string = preg_replace('!\s!u', '', $string);

        // Set the patterns we'll test against
        $patterns = [
            // Match any attribute starting with "on" or xmlns
            'on_events' => '#(<[^>]+[[a-z\x00-\x20\"\'\/])([\s\/]on|\sxmlns)[a-z].*=>?#iUu',

            // Match javascript:, livescript:, vbscript:, mocha:, feed: and data: protocols
            'invalid_protocols' => '#(' . implode('|', array_map('preg_quote', $invalid_protocols, ['#'])) . ')(:|\&\#58)\S.*?#iUu',

            // Match -moz-bindings
            'moz_binding' => '#-moz-binding[a-z\x00-\x20]*:#u',

            // Match style attributes
            'html_inline_styles' => '#(<[^>]+[a-z\x00-\x20\"\'\/])(style=[^>]*(url\:|x\:expression).*)>?#iUu',

            // Match potentially dangerous tags
            'dangerous_tags' => '#</*(' . implode('|', array_map('preg_quote', $dangerous_tags, ['#'])) . ')[^>]*>?#ui'
        ];

        // Iterate over rules and return label if fail
        foreach ($patterns as $name => $regex) {
            if (!empty($enabled_rules[$name])) {
                if (preg_match($regex, $string) || preg_match($regex, $orig)) {
                    return $name;
                }
            }
        }

        return null;
    }

    public static function getXssDefaults(): array
    {
        /** @var Config $config */
        $config = Grav::instance()['config'];

        return [
            'enabled_rules' => $config->get('security.xss_enabled'),
            'dangerous_tags' => array_map('trim', $config->get('security.xss_dangerous_tags')),
            'invalid_protocols' => array_map('trim', $config->get('security.xss_invalid_protocols')),
        ];
    }
}
