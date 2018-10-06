<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use DateTime;
use Grav\Common\Helpers\Truncator;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

abstract class Utils
{
    protected static $nonces = [];

    /**
     * Simple helper method to make getting a Grav URL easier
     *
     * @param $input
     * @param bool $domain
     * @return bool|null|string
     */
    public static function url($input, $domain = false)
    {
        if (!trim((string)$input)) {
            return false;
        }

        if (Grav::instance()['config']->get('system.absolute_urls', false)) {
            $domain = true;
        }

        if (Grav::instance()['uri']->isExternal($input)) {
            return $input;
        }

        $input = ltrim((string)$input, '/');

        if (Utils::contains((string)$input, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];

            $parts = Uri::parseUrl($input);

            if ($parts) {
                $resource = $locator->findResource("{$parts['scheme']}://{$parts['host']}{$parts['path']}", false);

                if (isset($parts['query'])) {
                    $resource = $resource . '?' . $parts['query'];
                }
            } else {
                // Not a valid URL (can still be a stream).
                $resource = $locator->findResource($input, false);
            }


        } else {
            $resource = $input;
        }

        /** @var Uri $uri */
        $uri = Grav::instance()['uri'];

        return $resource ? rtrim($uri->rootUrl($domain), '/') . '/' . $resource : null;
    }

    /**
     * Check if the $haystack string starts with the substring $needle
     *
     * @param  string $haystack
     * @param  string|string[] $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        $status = false;

        foreach ((array)$needle as $each_needle) {
            $status = $each_needle === '' || strpos($haystack, $each_needle) === 0;
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
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        $status = false;

        foreach ((array)$needle as $each_needle) {
            $status = $each_needle === '' || substr($haystack, -strlen($each_needle)) === $each_needle;
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
     *
     * @return bool
     */
    public static function contains($haystack, $needle)
    {
        $status = false;

        foreach ((array)$needle as $each_needle) {
            $status = $each_needle === '' || strpos($haystack, $each_needle) !== false;
            if ($status) {
                break;
            }
        }

        return $status;
    }

    /**
     * Returns the substring of a string up to a specified needle.  if not found, return the whole haystack
     *
     * @param $haystack
     * @param $needle
     *
     * @return string
     */
    public static function substrToString($haystack, $needle)
    {
        if (static::contains($haystack, $needle)) {
            return substr($haystack, 0, strpos($haystack, $needle));
        }

        return $haystack;
    }

    /**
     * Utility method to replace only the first occurrence in a string
     *
     * @param $search
     * @param $replace
     * @param $subject
     * @return mixed
     */
    public static function replaceFirstOccurrence($search, $replace, $subject)
    {
        if (!$search) {
            return $subject;
        }
        $pos = strpos($subject, $search);
        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }

    /**
     * Utility method to replace only the last occurrence in a string
     *
     * @param $search
     * @param $replace
     * @param $subject
     * @return mixed
     */
    public static function replaceLastOccurrence($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if($pos !== false)
        {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
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
     * Recursive Merge with uniqueness
     *
     * @param $array1
     * @param $array2
     * @return mixed
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
     * Return the Grav date formats allowed
     *
     * @return array
     */
    public static function dateFormats()
    {
        $now = new DateTime();

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
    public static function truncate($string, $limit = 150, $up_to_break = false, $break = " ", $pad = "&hellip;")
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
        $root = ($path[0] === '/') ? '/' : '';

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

        return $root . implode('/', $ret);
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
        return in_array($function, explode(',', ini_get('disable_functions')), true);
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
        $testDate = new \DateTime;

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
        foreach ($array as $key => $inner){
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
     * Checks if the passed path contains the language code prefix
     *
     * @param string $string The path
     *
     * @return bool
     */
    public static function pathPrefixedByLangCode($string)
    {
        if (strlen($string) <= 3) {
            return false;
        }

        $languages_enabled = Grav::instance()['config']->get('system.languages.supported', []);

        if ($string[0] === '/' && $string[3] === '/' && in_array(substr($string, 1, 2), $languages_enabled)) {
            return true;
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
            $datetime = DateTime::createFromFormat($dateformat, $date);
        } else {
            $datetime = new DateTime($date);
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
     * @deprecated Use getDotNotation() method instead
     */
    public static function resolve(array $array, $path, $default = null)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use getDotNotation() method instead', E_USER_DEPRECATED);

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
        $username = '';
        if (isset(Grav::instance()['user'])) {
            $user = Grav::instance()['user'];
            $username = $user->username;
        }

        $token = session_id();
        $i = self::nonceTick();

        if ($previousTick) {
            $i--;
        }

        return ($i . '|' . $action . '|' . $username . '|' . $token . '|' . Grav::instance()['config']->get('security.salt'));
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
        if ($nonce === self::getNonce($action, $previousTick)) {
            return true;
        }

        //Invalid nonce
        return false;
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
     * @param $array
     * @param $key
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
     * @param      $array
     * @param      $key
     * @param      $value
     * @param bool $merge
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
     * @param $array
     * @param $array_key
     * @param int $direction
     * @param int $sort_flags
     * @return array
     */
    public static function sortArrayByKey($array, $array_key, $direction = SORT_DESC, $sort_flags = SORT_REGULAR )
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
     * Get's path based on a token
     *
     * @param $path
     * @param Page|null $page
     * @return string
     * @throws \RuntimeException
     */
    public static function getPagePathFromToken($path, $page = null)
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
            }

            $upload_max = static::parseSize(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }

        return $max_size;
    }

    /**
     * Parse a readable file size and return a value in bytes
     *
     * @param $size
     * @return int
     */
    public static function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return (int)($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return (int)$size;
    }

    /**
     * Multibyte-safe Parse URL function
     *
     * @param $url
     * @return mixed
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
}
