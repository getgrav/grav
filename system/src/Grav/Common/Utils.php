<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Helpers\Truncator;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Page\Markdown\Excerpts;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

abstract class Utils
{
    protected static $nonces = [];

    protected const ROOTURL_REGEX = '{^((?:http[s]?:\/\/[^\/]+)|(?:\/\/[^\/]+))(.*)}';

    // ^((?:http[s]?:)?[\/]?(?:\/))

    /**
     * Simple helper method to make getting a Grav URL easier
     *
     * @param string|object $input
     * @param bool $domain
     * @param bool $fail_gracefully
     * @return bool|null|string
     */
    public static function url($input, $domain = false, $fail_gracefully = false)
    {
        if ((!is_string($input) && !method_exists($input, '__toString')) || !trim($input)) {
            if ($fail_gracefully) {
                $input = '/';
            } else {
                return false;
            }
        }

        $input = (string)$input;

        if (Uri::isExternal($input)) {
            return $input;
        }

        $grav = Grav::instance();

        /** @var Uri $uri */
        $uri = $grav['uri'];

        if (static::contains((string)$input, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            $parts = Uri::parseUrl($input);

            if (is_array($parts)) {
                // Make sure we always have scheme, host, port and path.
                $scheme = $parts['scheme'] ?? '';
                $host = $parts['host'] ?? '';
                $port = $parts['port'] ?? '';
                $path = $parts['path'] ?? '';

                if ($scheme && !$port) {
                    // If URL has a scheme, we need to check if it's one of Grav streams.
                    if (!$locator->schemeExists($scheme)) {
                        // If scheme does not exists as a stream, assume it's external.
                        return str_replace(' ', '%20', $input);
                    }

                    // Attempt to find the resource (because of parse_url() we need to put host back to path).
                    $resource = $locator->findResource("{$scheme}://{$host}{$path}", false);

                    if ($resource === false) {
                        if (!$fail_gracefully) {
                            return false;
                        }

                        // Return location where the file would be if it was saved.
                        $resource = $locator->findResource("{$scheme}://{$host}{$path}", false, true);
                    }

                } elseif ($host || $port) {
                    // If URL doesn't have scheme but has host or port, it is external.
                    return str_replace(' ', '%20', $input);
                }

                if (!empty($resource)) {
                    // Add query string back.
                    if (isset($parts['query'])) {
                        $resource .= '?' . $parts['query'];
                    }

                    // Add fragment back.
                    if (isset($parts['fragment'])) {
                        $resource .= '#' . $parts['fragment'];
                    }
                }

            } else {
                // Not a valid URL (can still be a stream).
                $resource = $locator->findResource($input, false);
            }

        } else {
            $root = $uri->rootUrl();

            if (static::startsWith($input, $root)) {
                $input = static::replaceFirstOccurrence($root, '', $input);
            }

            $input = ltrim($input, '/');

            $resource = $input;
        }

        if (!$fail_gracefully && $resource === false) {
            return false;
        }

        $domain = $domain ?: $grav['config']->get('system.absolute_urls', false);

        return rtrim($uri->rootUrl($domain), '/') . '/' . ($resource ?? '');
    }

    /**
     * Check if the $haystack string starts with the substring $needle
     *
     * @param  string $haystack
     * @param  string|string[] $needle
     * @param bool $case_sensitive
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle, $case_sensitive = true)
    {
        $status = false;

        $compare_func = $case_sensitive ? 'mb_strpos' : 'mb_stripos';

        foreach ((array)$needle as $each_needle) {
            $status = $each_needle === '' || $compare_func($haystack, $each_needle) === 0;
            if ($status) {
                break;
            }
        }

        return $status;
    }

    /**
     * Check if the $haystack string ends with the substring $needle
     *
     * @param  string $haystack
     * @param  string|string[] $needle
     * @param bool $case_sensitive
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle, $case_sensitive = true)
    {
        $status = false;

        $compare_func = $case_sensitive ? 'mb_strrpos' : 'mb_strripos';

        foreach ((array)$needle as $each_needle) {
            $expectedPosition = mb_strlen($haystack) - mb_strlen($each_needle);
            $status = $each_needle === '' || $compare_func($haystack, $each_needle, 0) === $expectedPosition;
            if ($status) {
                break;
            }
        }

        return $status;
    }

    /**
     * Check if the $haystack string contains the substring $needle
     *
     * @param  string $haystack
     * @param  string|string[] $needle
     * @param  bool $case_sensitive
     *
     * @return bool
     */
    public static function contains($haystack, $needle, $case_sensitive = true)
    {
        $status = false;

        $compare_func = $case_sensitive ? 'mb_strpos' : 'mb_stripos';

        foreach ((array)$needle as $each_needle) {
            $status = $each_needle === '' || $compare_func($haystack, $each_needle) !== false;
            if ($status) {
                break;
            }
        }

        return $status;
    }

    /**
     * Function that can match wildcards
     *
     * match_wildcard('foo*', $test),      // TRUE
     * match_wildcard('bar*', $test),      // FALSE
     * match_wildcard('*bar*', $test),     // TRUE
     * match_wildcard('**blob**', $test),  // TRUE
     * match_wildcard('*a?d*', $test),     // TRUE
     * match_wildcard('*etc**', $test)     // TRUE
     *
     * @param string $wildcard_pattern
     * @param string $haystack
     * @return false|int
     */
    public static function matchWildcard($wildcard_pattern, $haystack) {
        $regex = str_replace(
            array("\*", "\?"), // wildcard chars
            array('.*','.'),   // regexp chars
            preg_quote($wildcard_pattern, '/')
        );

        return preg_match('/^'.$regex.'$/is', $haystack);
    }

    /**
     * Returns the substring of a string up to a specified needle.  if not found, return the whole haystack
     *
     * @param string $haystack
     * @param string $needle
     * @param bool $case_sensitive
     *
     * @return string
     */
    public static function substrToString($haystack, $needle, $case_sensitive = true)
    {
        $compare_func = $case_sensitive ? 'mb_strpos' : 'mb_stripos';

        if (static::contains($haystack, $needle, $case_sensitive)) {
            return mb_substr($haystack, 0, $compare_func($haystack, $needle, $case_sensitive));
        }

        return $haystack;
    }

    /**
     * Utility method to replace only the first occurrence in a string
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function replaceFirstOccurrence($search, $replace, $subject)
    {
        if (!$search) {
            return $subject;
        }

        $pos = mb_strpos($subject, $search);
        if ($pos !== false) {
            $subject = static::mb_substr_replace($subject, $replace, $pos, mb_strlen($search));
        }


        return $subject;
    }

    /**
     * Utility method to replace only the last occurrence in a string
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    public static function replaceLastOccurrence($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if($pos !== false)
        {
            $subject = static::mb_substr_replace($subject, $replace, $pos, mb_strlen($search));
        }

        return $subject;
    }

    /**
     * Multibyte compatible substr_replace
     *
     * @param string $original
     * @param string $replacement
     * @param int $position
     * @param int $length
     * @return string
     */
    public static function mb_substr_replace($original, $replacement, $position, $length)
    {
        $startString = mb_substr($original, 0, $position, "UTF-8");
        $endString = mb_substr($original, $position + $length, mb_strlen($original), "UTF-8");

        return $startString . $replacement . $endString;
    }

    /**
     * Merge two objects into one.
     *
     * @param  object $obj1
     * @param  object $obj2
     *
     * @return object
     */
    public static function mergeObjects($obj1, $obj2)
    {
        return (object)array_merge((array)$obj1, (array)$obj2);
    }

    /**
     * Lowercase an entire array. Useful when combined with `in_array()`
     *
     * @param array $a
     * @return array|false
     */
    public static function arrayLower(Array $a)
    {
        return array_map('mb_strtolower', $a);
    }

    /**
     * Simple function to remove item/s in an array by value
     *
     * @param $search array
     * @param $value string|array
     * @return array
     */
    public static function arrayRemoveValue(Array $search, $value)
    {
        foreach ((array) $value as $val) {
            $key = array_search($val, $search);
            if ($key !== false) {
                unset($search[$key]);
            }
        }
        return $search;
    }

    /**
     * Recursive Merge with uniqueness
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function arrayMergeRecursiveUnique($array1, $array2)
    {
        if (empty($array1)) {
            // Optimize the base case
            return $array2;
        }

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $value = static::arrayMergeRecursiveUnique($array1[$key], $value);
            }
            $array1[$key] = $value;
        }

        return $array1;
    }

    /**
     * Returns an array with the differences between $array1 and $array2
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function arrayDiffMultidimensional($array1, $array2)
    {
        $result = array();
        foreach ($array1 as $key => $value) {
            if (!is_array($array2) || !array_key_exists($key, $array2)) {
                $result[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $recursiveArrayDiff = static::ArrayDiffMultidimensional($value, $array2[$key]);
                if (count($recursiveArrayDiff)) {
                    $result[$key] = $recursiveArrayDiff;
                }
                continue;
            }
            if ($value != $array2[$key]) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Array combine but supports different array lengths
     *
     * @param  array $arr1
     * @param  array $arr2
     * @return array|false
     */
    public static function arrayCombine($arr1, $arr2)
    {
        $count = min(count($arr1), count($arr2));

        return array_combine(array_slice($arr1, 0, $count), array_slice($arr2, 0, $count));
    }

    /**
     * Array is associative or not
     *
     * @param  array $arr
     * @return bool
     */
    public static function arrayIsAssociative($arr)
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Return the Grav date formats allowed
     *
     * @return array
     */
    public static function dateFormats()
    {
        $now = new \DateTime();

        $date_formats = [
            'd-m-Y H:i' => 'd-m-Y H:i (e.g. '.$now->format('d-m-Y H:i').')',
            'Y-m-d H:i' => 'Y-m-d H:i (e.g. '.$now->format('Y-m-d H:i').')',
            'm/d/Y h:i a' => 'm/d/Y h:i a (e.g. '.$now->format('m/d/Y h:i a').')',
            'H:i d-m-Y' => 'H:i d-m-Y (e.g. '.$now->format('H:i d-m-Y').')',
            'h:i a m/d/Y' => 'h:i a m/d/Y (e.g. '.$now->format('h:i a m/d/Y').')',
            ];
        $default_format = Grav::instance()['config']->get('system.pages.dateformat.default');
        if ($default_format) {
            $date_formats = array_merge([$default_format => $default_format.' (e.g. '.$now->format($default_format).')'], $date_formats);
        }

        return $date_formats;
    }

    /**
     * Get current date/time
     *
     * @param string|null $default_format
     * @return string
     * @throws \Exception
     */
    public static function dateNow($default_format = null)
    {
        $now = new \DateTime();

        if (is_null($default_format)) {
            $default_format = Grav::instance()['config']->get('system.pages.dateformat.default');
        }

        return $now->format($default_format);
    }

    /**
     * Truncate text by number of characters but can cut off words.
     *
     * @param  string $string
     * @param  int    $limit       Max number of characters.
     * @param  bool   $up_to_break truncate up to breakpoint after char count
     * @param  string $break       Break point.
     * @param  string $pad         Appended padding to the end of the string.
     *
     * @return string
     */
    public static function truncate($string, $limit = 150, $up_to_break = false, $break = ' ', $pad = '&hellip;')
    {
        // return with no change if string is shorter than $limit
        if (mb_strlen($string) <= $limit) {
            return $string;
        }

        // is $break present between $limit and the end of the string?
        if ($up_to_break && false !== ($breakpoint = mb_strpos($string, $break, $limit))) {
            if ($breakpoint < mb_strlen($string) - 1) {
                $string = mb_substr($string, 0, $breakpoint) . $pad;
            }
        } else {
            $string = mb_substr($string, 0, $limit) . $pad;
        }

        return $string;
    }

    /**
     * Truncate text by number of characters in a "word-safe" manor.
     *
     * @param string $string
     * @param int    $limit
     *
     * @return string
     */
    public static function safeTruncate($string, $limit = 150)
    {
        return static::truncate($string, $limit, true);
    }


    /**
     * Truncate HTML by number of characters. not "word-safe"!
     *
     * @param  string $text
     * @param  int $length in characters
     * @param  string $ellipsis
     *
     * @return string
     */
    public static function truncateHtml($text, $length = 100, $ellipsis = '...')
    {
        return Truncator::truncateLetters($text, $length, $ellipsis);
    }

    /**
     * Truncate HTML by number of characters in a "word-safe" manor.
     *
     * @param  string $text
     * @param  int    $length in words
     * @param  string $ellipsis
     *
     * @return string
     */
    public static function safeTruncateHtml($text, $length = 25, $ellipsis = '...')
    {
        return Truncator::truncateWords($text, $length, $ellipsis);
    }

    /**
     * Generate a random string of a given length
     *
     * @param int $length
     *
     * @return string
     */
    public static function generateRandomString($length = 5)
    {
        return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }

    /**
     * Provides the ability to download a file to the browser
     *
     * @param string $file the full path to the file to be downloaded
     * @param bool $force_download as opposed to letting browser choose if to download or render
     * @param int $sec      Throttling, try 0.1 for some speed throttling of downloads
     * @param int $bytes    Size of chunks to send in bytes. Default is 1024
     * @throws \Exception
     */
    public static function download($file, $force_download = true, $sec = 0, $bytes = 1024)
    {
        if (file_exists($file)) {
            // fire download event
            Grav::instance()->fireEvent('onBeforeDownload', new Event(['file' => $file]));

            $file_parts = pathinfo($file);
            $mimetype = static::getMimeByExtension($file_parts['extension']);
            $size   = filesize($file); // File size

            // clean all buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // required for IE, otherwise Content-Disposition may be ignored
            if (ini_get('zlib.output_compression')) {
                ini_set('zlib.output_compression', 'Off');
            }

            header('Content-Type: ' . $mimetype);
            header('Accept-Ranges: bytes');

            if ($force_download) {
                // output the regular HTTP headers
                header('Content-Disposition: attachment; filename="' . $file_parts['basename'] . '"');
            }

            // multipart-download and download resuming support
            if (isset($_SERVER['HTTP_RANGE'])) {
                list($a, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                list($range) = explode(',', $range, 2);
                list($range, $range_end) = explode('-', $range);
                $range = (int)$range;
                if (!$range_end) {
                    $range_end = $size - 1;
                } else {
                    $range_end = (int)$range_end;
                }
                $new_length = $range_end - $range + 1;
                header('HTTP/1.1 206 Partial Content');
                header("Content-Length: {$new_length}");
                header("Content-Range: bytes {$range}-{$range_end}/{$size}");
            } else {
                $range = 0;
                $new_length = $size;
                header('Content-Length: ' . $size);

                if (Grav::instance()['config']->get('system.cache.enabled')) {
                    $expires = Grav::instance()['config']->get('system.pages.expires');
                    if ($expires > 0) {
                        $expires_date = gmdate('D, d M Y H:i:s T', time() + $expires);
                        header('Cache-Control: max-age=' . $expires);
                        header('Expires: ' . $expires_date);
                        header('Pragma: cache');
                    }
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($file)));

                    // Return 304 Not Modified if the file is already cached in the browser
                    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
                        strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= filemtime($file))
                    {
                        header('HTTP/1.1 304 Not Modified');
                        exit();
                    }
                }
            }

            /* output the file itself */
            $chunksize = $bytes * 8; //you may want to change this
            $bytes_send = 0;

            $fp = @fopen($file, 'rb');
            if ($fp) {
                if ($range) {
                    fseek($fp, $range);
                }
                while (!feof($fp) && (!connection_aborted()) && ($bytes_send < $new_length) ) {
                    $buffer = fread($fp, $chunksize);
                    echo($buffer); //echo($buffer); // is also possible
                    flush();
                    usleep($sec * 1000000);
                    $bytes_send += strlen($buffer);
                }
                fclose($fp);
            } else {
                throw new \RuntimeException('Error - can not open file.');
            }

            exit;
        }
    }

    /**
     * Return the mimetype based on filename extension
     *
     * @param string $extension Extension of file (eg "txt")
     * @param string $default
     *
     * @return string
     */
    public static function getMimeByExtension($extension, $default = 'application/octet-stream')
    {
        $extension = strtolower($extension);

        // look for some standard types
        switch ($extension) {
            case null:
                return $default;
            case 'json':
                return 'application/json';
            case 'html':
                return 'text/html';
            case 'atom':
                return 'application/atom+xml';
            case 'rss':
                return 'application/rss+xml';
            case 'xml':
                return 'application/xml';
        }

        $media_types = Grav::instance()['config']->get('media.types');

        if (isset($media_types[$extension])) {
            if (isset($media_types[$extension]['mime'])) {
                return $media_types[$extension]['mime'];
            }
        }

        return $default;
    }

    /**
     * Get all the mimetypes for an array of extensions
     *
     * @param array $extensions
     * @return array
     */
    public static function getMimeTypes(array $extensions)
    {
        $mimetypes = [];
        foreach ($extensions as $extension) {
            $mimetype = static::getMimeByExtension($extension, false);
            if ($mimetype && !in_array($mimetype, $mimetypes)) {
                $mimetypes[] = $mimetype;
            }
        }
        return $mimetypes;
    }


    /**
     * Return the mimetype based on filename extension
     *
     * @param string $mime mime type (eg "text/html")
     * @param string $default default value
     *
     * @return string
     */
    public static function getExtensionByMime($mime, $default = 'html')
    {
        $mime = strtolower($mime);

        // look for some standard mime types
        switch ($mime) {
            case '*/*':
            case 'text/*':
            case 'text/html':
                return 'html';
            case 'application/json':
                return 'json';
            case 'application/atom+xml':
                return 'atom';
            case 'application/rss+xml':
                return 'rss';
            case 'application/xml':
                return 'xml';
        }

        $media_types = (array)Grav::instance()['config']->get('media.types');

        foreach ($media_types as $extension => $type) {
            if ($extension === 'defaults') {
                continue;
            }
            if (isset($type['mime']) && $type['mime'] === $mime) {
                return $extension;
            }
        }

        return $default;
    }

    /**
     * Get all the extensions for an array of mimetypes
     *
     * @param array $mimetypes
     * @return array
     */
    public static function getExtensions(array $mimetypes)
    {
        $extensions = [];
        foreach ($mimetypes as $mimetype) {
            $extension = static::getExtensionByMime($mimetype, false);
            if ($extension && !\in_array($extension, $extensions, true)) {
                $extensions[] = $extension;
            }
        }

        return $extensions;
    }

    /**
     * Return the mimetype based on filename
     *
     * @param string $filename Filename or path to file
     * @param string $default default value
     *
     * @return string
     */
    public static function getMimeByFilename($filename, $default = 'application/octet-stream')
    {
        return static::getMimeByExtension(pathinfo($filename, PATHINFO_EXTENSION), $default);
    }

    /**
     * Return the mimetype based on existing local file
     *
     * @param string $filename Path to the file
     *
     * @return string|bool
     */
    public static function getMimeByLocalFile($filename, $default = 'application/octet-stream')
    {
        $type = false;

        // For local files we can detect type by the file content.
        if (!stream_is_local($filename) || !file_exists($filename)) {
            return false;
        }

        // Prefer using finfo if it exists.
        if (\extension_loaded('fileinfo')) {
            $finfo = finfo_open(FILEINFO_SYMLINK | FILEINFO_MIME_TYPE);
            $type = finfo_file($finfo, $filename);
            finfo_close($finfo);
        } else {
            // Fall back to use getimagesize() if it is available (not recommended, but better than nothing)
            $info = @getimagesize($filename);
            if ($info) {
                $type = $info['mime'];
            }
        }

        return $type ?: static::getMimeByFilename($filename, $default);
    }


    /**
     * Returns true if filename is considered safe.
     *
     * @param string $filename
     * @return bool
     */
    public static function checkFilename($filename)
    {
        $dangerous_extensions = Grav::instance()['config']->get('security.uploads_dangerous_extensions', []);
        array_walk($dangerous_extensions, function(&$val) {
            $val = '.' . $val;
        });

        $extension = '.' . pathinfo($filename, PATHINFO_EXTENSION);

        return !(
            // Empty filenames are not allowed.
            !$filename
            // Filename should not contain horizontal/vertical tabs, newlines, nils or back/forward slashes.
            || strtr($filename, "\t\v\n\r\0\\/", '_______') !== $filename
            // Filename should not start or end with dot or space.
            || trim($filename, '. ') !== $filename
            // Filename should not contain .php in it.
            || static::contains($extension, $dangerous_extensions)
        );
    }

    /**
     * Normalize path by processing relative `.` and `..` syntax and merging path
     *
     * @param string $path
     *
     * @return string
     */
    public static function normalizePath($path)
    {
        // Resolve any streams
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        if ($locator->isStream($path)) {
            $path = $locator->findResource($path);
        }

        // Set root properly for any URLs
        $root = '';
        preg_match(self::ROOTURL_REGEX, $path, $matches);
        if ($matches) {
            $root = $matches[1];
            $path = $matches[2];
        }

        // Strip off leading / to ensure explode is accurate
        if (Utils::startsWith($path,'/')) {
            $root .= '/';
            $path = ltrim($path, '/');
        }

        // If there are any relative paths (..) handle those
        if (Utils::contains($path, '..')) {
            $segments = explode('/', trim($path, '/'));
            $ret = [];
            foreach ($segments as $segment) {
                if (($segment === '.') || $segment === '') {
                    continue;
                }
                if ($segment === '..') {
                    array_pop($ret);
                } else {
                    $ret[] = $segment;
                }
            }
            $path = implode('/', $ret);
        }

        // Stick everything back together
        $normalized = $root . $path;
        return $normalized;
    }

    /**
     * Check whether a function is disabled in the PHP settings
     *
     * @param string $function the name of the function to check
     *
     * @return bool
     */
    public static function isFunctionDisabled($function)
    {
        return \in_array($function, explode(',', ini_get('disable_functions')), true);
    }

    /**
     * Get the formatted timezones list
     *
     * @return array
     */
    public static function timezones()
    {
        $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        $offsets = [];
        $testDate = new \DateTime();

        foreach ($timezones as $zone) {
            $tz = new \DateTimeZone($zone);
            $offsets[$zone] = $tz->getOffset($testDate);
        }

        asort($offsets);

        $timezone_list = [];
        foreach ($offsets as $timezone => $offset) {
            $offset_prefix = $offset < 0 ? '-' : '+';
            $offset_formatted = gmdate('H:i', abs($offset));

            $pretty_offset = "UTC${offset_prefix}${offset_formatted}";

            $timezone_list[$timezone] = "(${pretty_offset}) ".str_replace('_', ' ', $timezone);
        }

        return $timezone_list;
    }

    /**
     * Recursively filter an array, filtering values by processing them through the $fn function argument
     *
     * @param array    $source the Array to filter
     * @param callable $fn     the function to pass through each array item
     *
     * @return array
     */
    public static function arrayFilterRecursive(Array $source, $fn)
    {
        $result = [];
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $result[$key] = static::arrayFilterRecursive($value, $fn);
                continue;
            }
            if ($fn($key, $value)) {
                $result[$key] = $value; // KEEP
                continue;
            }
        }

        return $result;
    }

    /**
     * Flatten an array
     *
     * @param array $array
     * @return array
     */
    public static function arrayFlatten($array)
    {
        $flatten = array();
        foreach ($array as $key => $inner) {
            if (is_array($inner)) {
                foreach ($inner as $inner_key => $value) {
                    $flatten[$inner_key] = $value;
                }
            } else {
                $flatten[$key] = $inner;
            }
        }

        return $flatten;
    }

    /**
     * Flatten a multi-dimensional associative array into dot notation
     *
     * @param  array   $array
     * @param  string  $prepend
     * @return array
     */
    public static function arrayFlattenDotNotation($array, $prepend = '')
    {
        $results = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $results = array_merge($results, static::arrayFlattenDotNotation($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Opposite of flatten, convert flat dot notation array to multi dimensional array
     *
     * @param array $array
     * @param string $separator
     * @return array
     */
    public static function arrayUnflattenDotNotation($array, $separator = '.')
    {
        $newArray = [];
        foreach ($array as $key => $value) {
            $dots = explode($separator, $key);
            if (\count($dots) > 1) {
                $last = &$newArray[$dots[0]];
                foreach ($dots as $k => $dot) {
                    if ($k === 0) {
                        continue;
                    }
                    $last = &$last[$dot];
                }
                $last = $value;
            } else {
                $newArray[$key] = $value;
            }
        }

        return $newArray;
    }

    /**
     * Checks if the passed path contains the language code prefix
     *
     * @param string $string The path
     *
     * @return bool|string Either false or the language
     *
     */
    public static function pathPrefixedByLangCode($string)
    {
        $languages_enabled = Grav::instance()['config']->get('system.languages.supported', []);
        $parts = explode('/', trim($string, '/'));

        if (count($parts) > 0 && in_array($parts[0], $languages_enabled)) {
            return $parts[0];
        }

        return false;
    }

    /**
     * Get the timestamp of a date
     *
     * @param string $date a String expressed in the system.pages.dateformat.default format, with fallback to a
     *                     strtotime argument
     * @param string $format a date format to use if possible
     * @return int the timestamp
     */
    public static function date2timestamp($date, $format = null)
    {
        $config = Grav::instance()['config'];
        $dateformat = $format ?: $config->get('system.pages.dateformat.default');

        // try to use DateTime and default format
        if ($dateformat) {
            $datetime = \DateTime::createFromFormat($dateformat, $date);
        } else {
            $datetime = new \DateTime($date);
        }

        // fallback to strtotime() if DateTime approach failed
        if ($datetime !== false) {
            return $datetime->getTimestamp();
        }

        return strtotime($date);
    }

    /**
     * @param array $array
     * @param string $path
     * @param null $default
     * @return mixed
     *
     * @deprecated 1.5 Use ->getDotNotation() method instead.
     */
    public static function resolve(array $array, $path, $default = null)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use ->getDotNotation() method instead', E_USER_DEPRECATED);

        return static::getDotNotation($array, $path, $default);
    }

    /**
     * Checks if a value is positive
     *
     * @param string $value
     *
     * @return boolean
     */
    public static function isPositive($value)
    {
        return in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true);
    }

    /**
     * Generates a nonce string to be hashed. Called by self::getNonce()
     * We removed the IP portion in this version because it causes too many inconsistencies
     * with reverse proxy setups.
     *
     * @param string $action
     * @param bool   $previousTick if true, generates the token for the previous tick (the previous 12 hours)
     *
     * @return string the nonce string
     */
    private static function generateNonceString($action, $previousTick = false)
    {
        $grav = Grav::instance();

        $username = isset($grav['user']) ? $grav['user']->username : '';
        $token = session_id();
        $i = self::nonceTick();

        if ($previousTick) {
            $i--;
        }

        return ($i . '|' . $action . '|' . $username . '|' . $token . '|' . $grav['config']->get('security.salt'));
    }

    /**
     * Get the time-dependent variable for nonce creation.
     *
     * Now a tick lasts a day. Once the day is passed, the nonce is not valid any more. Find a better way
     *       to ensure nonces issued near the end of the day do not expire in that small amount of time
     *
     * @return int the time part of the nonce. Changes once every 24 hours
     */
    private static function nonceTick()
    {
        $secondsInHalfADay = 60 * 60 * 12;

        return (int)ceil(time() / $secondsInHalfADay);
    }

    /**
     * Creates a hashed nonce tied to the passed action. Tied to the current user and time. The nonce for a given
     * action is the same for 12 hours.
     *
     * @param string $action      the action the nonce is tied to (e.g. save-user-admin or move-page-homepage)
     * @param bool   $previousTick if true, generates the token for the previous tick (the previous 12 hours)
     *
     * @return string the nonce
     */
    public static function getNonce($action, $previousTick = false)
    {
        // Don't regenerate this again if not needed
        if (isset(static::$nonces[$action][$previousTick])) {
            return static::$nonces[$action][$previousTick];
        }
        $nonce = md5(self::generateNonceString($action, $previousTick));
        static::$nonces[$action][$previousTick] = $nonce;

        return static::$nonces[$action][$previousTick];
    }

    /**
     * Verify the passed nonce for the give action
     *
     * @param string|string[] $nonce  the nonce to verify
     * @param string $action the action to verify the nonce to
     *
     * @return boolean verified or not
     */
    public static function verifyNonce($nonce, $action)
    {
        //Safety check for multiple nonces
        if (is_array($nonce)) {
            $nonce = array_shift($nonce);
        }

        //Nonce generated 0-12 hours ago
        if ($nonce === self::getNonce($action)) {
            return true;
        }

        //Nonce generated 12-24 hours ago
        $previousTick = true;

        return $nonce === self::getNonce($action, $previousTick);
    }

    /**
     * Simple helper method to get whether or not the admin plugin is active
     *
     * @return bool
     */
    public static function isAdminPlugin()
    {
        if (isset(Grav::instance()['admin'])) {
            return true;
        }

        return false;
    }

    /**
     * Get a portion of an array (passed by reference) with dot-notation key
     *
     * @param array $array
     * @param string|int $key
     * @param null $default
     * @return mixed
     */
    public static function getDotNotation($array, $key, $default = null)
    {
        if (null === $key) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set portion of array (passed by reference) for a dot-notation key
     * and set the value
     *
     * @param array      $array
     * @param string|int $key
     * @param mixed      $value
     * @param bool       $merge
     *
     * @return mixed
     */
    public static function setDotNotation(&$array, $key, $value, $merge = false)
    {
        if (null === $key) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if ( ! isset($array[$key]) || ! is_array($array[$key]))
            {
                $array[$key] = array();
            }

            $array =& $array[$key];
        }

        $key = array_shift($keys);

        if (!$merge || !isset($array[$key])) {
            $array[$key] = $value;
        } else {
            $array[$key] = array_merge($array[$key], $value);
        }

        return $array;
    }

    /**
     * Utility method to determine if the current OS is Windows
     *
     * @return bool
     */
    public static function isWindows()
    {
        return strncasecmp(PHP_OS, 'WIN', 3) === 0;
    }

    /**
     * Utility to determine if the server running PHP is Apache
     *
     * @return bool
     */
    public static function isApache() {
        return isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false;
    }

    /**
     * Sort a multidimensional array  by another array of ordered keys
     *
     * @param array $array
     * @param array $orderArray
     * @return array
     */
    public static function sortArrayByArray(array $array, array $orderArray)
    {
        $ordered = array();
        foreach ($orderArray as $key) {
            if (array_key_exists($key, $array)) {
                $ordered[$key] = $array[$key];
                unset($array[$key]);
            }
        }
        return $ordered + $array;
    }

    /**
     * Sort an array by a key value in the array
     *
     * @param mixed $array
     * @param string|int $array_key
     * @param int $direction
     * @param int $sort_flags
     * @return array
     */
    public static function sortArrayByKey($array, $array_key, $direction = SORT_DESC, $sort_flags = SORT_REGULAR)
    {
        $output = [];

        if (!is_array($array) || !$array) {
            return $output;
        }

        foreach ($array as $key => $row) {
            $output[$key] = $row[$array_key];
        }

        array_multisort($output, $direction, $sort_flags, $array);

        return $array;
    }

    /**
     * Get path based on a token
     *
     * @param string $path
     * @param PageInterface|null $page
     * @return string
     * @throws \RuntimeException
     */
    public static function getPagePathFromToken($path, PageInterface $page = null)
    {
        $path_parts = pathinfo($path);
        $grav       = Grav::instance();

        $basename = '';
        if (isset($path_parts['extension'])) {
            $basename = '/' . $path_parts['basename'];
            $path     = rtrim($path_parts['dirname'], ':');
        }

        $regex = '/(@self|self@)|((?:@page|page@):(?:.*))|((?:@theme|theme@):(?:.*))/';
        preg_match($regex, $path, $matches);

        if ($matches) {
            if ($matches[1]) {
                if (null === $page) {
                    throw new \RuntimeException('Page not available for this self@ reference');
                }
            } elseif ($matches[2]) {
                // page@
                $parts = explode(':', $path);
                $route = $parts[1];
                $page  = $grav['page']->find($route);
            } elseif ($matches[3]) {
                // theme@
                $parts = explode(':', $path);
                $route = $parts[1];
                $theme = str_replace(ROOT_DIR, '', $grav['locator']->findResource("theme://"));

                return $theme . $route . $basename;
            }
        } else {
            return $path . $basename;
        }

        if (!$page) {
            throw new \RuntimeException('Page route not found: ' . $path);
        }

        $path = str_replace($matches[0], rtrim($page->relativePagePath(), '/'), $path);

        return $path . $basename;
    }

    public static function getUploadLimit()
    {
        static $max_size = -1;

        if ($max_size < 0) {
            $post_max_size = static::parseSize(ini_get('post_max_size'));
            if ($post_max_size > 0) {
                $max_size = $post_max_size;
            } else {
                $max_size = 0;
            }

            $upload_max = static::parseSize(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }

        return $max_size;
    }

    /**
     * Convert bytes to the unit specified by the $to parameter.
     *
     * @param int $bytes The filesize in Bytes.
     * @param string $to The unit type to convert to. Accepts K, M, or G for Kilobytes, Megabytes, or Gigabytes, respectively.
     * @param int $decimal_places The number of decimal places to return.
     *
     * @return int Returns only the number of units, not the type letter. Returns 0 if the $to unit type is out of scope.
     *
     */
    public static function convertSize($bytes, $to, $decimal_places = 1)
    {
        $formulas = array(
            'K' => number_format($bytes / 1024, $decimal_places),
            'M' => number_format($bytes / 1048576, $decimal_places),
            'G' => number_format($bytes / 1073741824, $decimal_places)
        );
        return $formulas[$to] ?? 0;
    }

    /**
     * Return a pretty size based on bytes
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function prettySize($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= 1024 ** $pow;
        // $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Parse a readable file size and return a value in bytes
     *
     * @param string|int $size
     * @return int
     */
    public static function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            $size = $size * pow(1024, stripos('bkmgtpezy', $unit[0]));
        }

        return (int) abs(round($size));
    }

    /**
     * Multibyte-safe Parse URL function
     *
     * @param string $url
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function multibyteParseUrl($url)
    {
        $enc_url = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) {
                return urlencode($matches[0]);
            },
            $url
        );

        $parts = parse_url($enc_url);

        if($parts === false) {
            throw new \InvalidArgumentException('Malformed URL: ' . $url);
        }

        foreach($parts as $name => $value) {
            $parts[$name] = urldecode($value);
        }

        return $parts;
    }

    /**
     * Process a string as markdown
     *
     * @param string $string
     *
     * @param bool $block Block or Line processing
     * @param null $page
     * @return string
     * @throws \Exception
     */
    public static function processMarkdown($string, $block = true, $page = null)
    {
        $grav = Grav::instance();
        $page     = $page ?? $grav['page'] ?? null;
        $defaults = [
            'markdown' => $grav['config']->get('system.pages.markdown', []),
            'images' => $grav['config']->get('system.images', [])
        ];
        $extra = $defaults['markdown']['extra'] ?? false;

        $excerpts = new Excerpts($page, $defaults);

        // Initialize the preferred variant of Parsedown
        if ($extra) {
            $parsedown = new ParsedownExtra($excerpts);
        } else {
            $parsedown = new Parsedown($excerpts);
        }

        if ($block) {
            $string = $parsedown->text($string);
        } else {
            $string = $parsedown->line($string);
        }

        return $string;
    }

    /**
     * Find the subnet of an ip with CIDR prefix size
     *
     * @param string $ip
     * @param int $prefix
     *
     * @return string
     */
    public static function getSubnet($ip, $prefix = 64)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Packed representation of IP
        $ip = inet_pton($ip);

        // Maximum netmask length = same as packed address
        $len = 8*strlen($ip);
        if ($prefix > $len) $prefix = $len;

        $mask  = str_repeat('f', $prefix>>2);

        switch($prefix & 3)
        {
            case 3: $mask .= 'e'; break;
            case 2: $mask .= 'c'; break;
            case 1: $mask .= '8'; break;
        }
        $mask = str_pad($mask, $len>>2, '0');

        // Packed representation of netmask
        $mask = pack('H*', $mask);
        // Bitwise - Take all bits that are both 1 to generate subnet
        $subnet = inet_ntop($ip & $mask);

        return $subnet;
    }

    /**
     * Wrapper to ensure html, htm in the front of the supported page types
     *
     * @param array|null $defaults
     * @return array|mixed
     */
    public static function getSupportPageTypes(array $defaults = null)
    {
        $types = Grav::instance()['config']->get('system.pages.types', $defaults);

        // remove html/htm
        $types = static::arrayRemoveValue($types, ['html', 'htm']);

        // put them back at the front
        $types = array_merge(['html', 'htm'], $types);

        return $types;
    }
}
