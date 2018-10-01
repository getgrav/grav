<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

class Security
{

    public static function detectXssFromPages($pages, callable $status = null)
    {
        $routes = $pages->routes();

        // Remove duplicate for homepage
        unset($routes['/']);

        $list = [];

//        // This needs Symfony 4.1 to work
//        $status && $status([
//            'type' => 'count',
//            'steps' => count($routes),
//        ]);

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
                    $list[$page->filePathClean()] = $results;
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
     * @return boolean|string       Type of XSS vector if the given `$string` may contain XSS, false otherwise.
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

        // Get XSS rules from security configuration
        $xss_rules = Grav::instance()['config']->get('security.xss_rules');

        // Iterate over rules and return label if fail
        foreach ((array) $xss_rules as $rule) {
            if ($rule['enabled'] === true) {
                $label = $rule['label'];
                $regex = $rule['regex'];

                if ($label && $regex) {
                    if (preg_match($regex, $string) || preg_match($regex, $orig)) {
                        return $label;
                    }
                }
            }
        }

        return false;
    }
}
