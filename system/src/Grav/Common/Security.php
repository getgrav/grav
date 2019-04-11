<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Page\Pages;

class Security
{

    public static function detectXssFromPages(Pages $pages, $route = true, callable $status = null)
    {
        $routes = $pages->routes();

        // Remove duplicate for homepage
        unset($routes['/']);

        $list = [];

        // This needs Symfony 4.1 to work
        $status && $status([
            'type' => 'count',
            'steps' => count($routes),
        ]);

        foreach ($routes as $path) {

            $status && $status([
                'type' => 'progress',
            ]);

            try {
                $page = $pages->get($path);

                // call the content to load/cache it
                $header = (array) $page->header();
                $content = $page->value('content');

                $data = ['header' => $header, 'content' => $content];
                $results = Security::detectXssFromArray($data);

                if (!empty($results)) {
                    if ($route) {
                        $list[$page->route()] = $results;
                    } else {
                        $list[$page->filePathClean()] = $results;
                    }

                }

            } catch (\Exception $e) {
                continue;
            }
        }

        return $list;
    }

    /**
     * @param array $array      Array such as $_POST or $_GET
     * @param string $prefix    Prefix for returned values.
     * @return array            Returns flatten list of potentially dangerous input values, such as 'data.content'.
     */
    public static function detectXssFromArray(array $array, $prefix = '')
    {
        $list = [];

        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $list[] = static::detectXssFromArray($value, $prefix . $key . '.');
            }
            if ($result = static::detectXss($value)) {
                $list[] = [$prefix . $key => $result];
            }
        }

        if (!empty($list)) {
            return array_merge(...$list);
        }

        return $list;
    }

    /**
     * Determine if string potentially has a XSS attack. This simple function does not catch all XSS and it is likely to
     * return false positives because of it tags all potentially dangerous HTML tags and attributes without looking into
     * their content.
     *
     * @param string $string The string to run XSS detection logic on
     * @return bool|string       Type of XSS vector if the given `$string` may contain XSS, false otherwise.
     *
     * Copies the code from: https://github.com/symphonycms/xssfilter/blob/master/extension.driver.php#L138
     */
    public static function detectXss($string)
    {
        // Skip any null or non string values
        if (null === $string || !\is_string($string) || empty($string)) {
            return false;
        }

        // Keep a copy of the original string before cleaning up
        $orig = $string;

        // URL decode
        $string = urldecode($string);

        // Convert Hexadecimals
        $string = (string)preg_replace_callback('!(&#|\\\)[xX]([0-9a-fA-F]+);?!u', function($m) {
            return \chr(hexdec($m[2]));
        }, $string);

        // Clean up entities
        $string = preg_replace('!(&#0+[0-9]+)!u','$1;', $string);

        // Decode entities
        $string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');

        // Strip whitespace characters
        $string = preg_replace('!\s!u','', $string);

        $config = Grav::instance()['config'];

        $dangerous_tags = array_map('preg_quote', array_map("trim", $config->get('security.xss_dangerous_tags')));
        $invalid_protocols =  array_map('preg_quote', array_map("trim", $config->get('security.xss_invalid_protocols')));
        $enabled_rules = $config->get('security.xss_enabled');

        // Set the patterns we'll test against
        $patterns = [
            // Match any attribute starting with "on" or xmlns
            'on_events' => '#(<[^>]+[[a-z\x00-\x20\"\'\/])(\son|\sxmlns)[a-z].*=>?#iUu',

            // Match javascript:, livescript:, vbscript:, mocha:, feed: and data: protocols
            'invalid_protocols' => '#(' . implode('|', $invalid_protocols) . '):.*?#iUu',

            // Match -moz-bindings
            'moz_binding' => '#-moz-binding[a-z\x00-\x20]*:#u',

            // Match style attributes
            'html_inline_styles' => '#(<[^>]+[a-z\x00-\x20\"\'\/])(style=[^>]*(url\:|x\:expression).*)>?#iUu',

            // Match potentially dangerous tags
            'dangerous_tags' => '#</*(' . implode('|', $dangerous_tags) . ')[^>]*>?#ui'
        ];


        // Iterate over rules and return label if fail
        foreach ((array) $patterns as $name => $regex) {
            if ($enabled_rules[$name] === true) {

                if (preg_match($regex, $string) || preg_match($regex, $orig)) {
                    return $name;
                }

            }
        }

        return false;
    }
}
