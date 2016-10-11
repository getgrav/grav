<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use DateTime;
use Grav\Common\Helpers\Truncator;
use RocketTheme\Toolbox\Event\Event;

abstract class Utils
{
    protected static $nonces = [];

    /**
     * Check if the $haystack string starts with the substring $needle
     *
     * @param  string $haystack
     * @param  string $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        if (is_array($needle)) {
            $status = false;
            foreach ($needle as $each_needle) {
                $status = $status || ($each_needle === '' || strpos($haystack, $each_needle) === 0);
                if ($status) {
                    return $status;
                }
            }

            return $status;
        }

        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    /**
     * Check if the $haystack string ends with the substring $needle
     *
     * @param  string $haystack
     * @param  string $needle
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        if (is_array($needle)) {
            $status = false;
            foreach ($needle as $each_needle) {
                $status = $status || ($each_needle === '' || substr($haystack, -strlen($each_needle)) === $each_needle);
                if ($status) {
                    return $status;
                }
            }

            return $status;
        }

        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Check if the $haystack string contains the substring $needle
     *
     * @param  string $haystack
     * @param  string $needle
     *
     * @return bool
     */
    public static function contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

    /**
     * Returns the substring of a string up to a specified needle.  if not found, return the whole haytack
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
                $string = mb_substr($string, 0, $breakpoint) . $break;
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
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
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
            $mimetype = Utils::getMimeByExtension($file_parts['extension']);
            $size   = filesize($file); // File size

            // clean all buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            // required for IE, otherwise Content-Disposition may be ignored
            if (ini_get('zlib.output_compression')) {
                ini_set('zlib.output_compression', 'Off');
            }

            header("Content-Type: " . $mimetype);
            header('Accept-Ranges: bytes');

            if ($force_download) {
                // output the regular HTTP headers
                header('Content-Disposition: attachment; filename="' . $file_parts['basename'] . '"');
            }

            // multipart-download and download resuming support
            if (isset($_SERVER['HTTP_RANGE'])) {
                list($a, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
                list($range) = explode(",", $range, 2);
                list($range, $range_end) = explode("-", $range);
                $range = intval($range);
                if (!$range_end) {
                    $range_end = $size - 1;
                } else {
                    $range_end = intval($range_end);
                }
                $new_length = $range_end - $range + 1;
                header("HTTP/1.1 206 Partial Content");
                header("Content-Length: $new_length");
                header("Content-Range: bytes $range-$range_end/$size");
            } else {
                $new_length = $size;
                header("Content-Length: " . $size);

                if (Grav::instance()['config']->get('system.cache.enabled')) {
                    $expires = Grav::instance()['config']->get('system.pages.expires');
                    if ($expires > 0) {
                        $expires_date = gmdate('D, d M Y H:i:s T', time() + $expires);
                        header('Cache-Control: max-age=' . $expires);
                        header('Expires: ' . $expires_date);
                        header('Pragma: cache');
                    }
                    header('Last-Modified: ' . gmdate("D, d M Y H:i:s T", filemtime($file)));

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

            $fp = @fopen($file, 'r');
            if ($fp) {
                if (isset($_SERVER['HTTP_RANGE'])) {
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
                throw new \Exception('Error - can not open file.');
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

        $media_types = Grav::instance()['config']->get('media.types');

        foreach ($media_types as $extension => $type) {
            if ($extension == 'defaults') {
                continue;
            }
            if (isset($type['mime']) && $type['mime'] == $mime) {
                return $extension;
            }
        }

        return $default;
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
            if (($segment == '.') || strlen($segment) == 0) {
                continue;
            }
            if ($segment == '..') {
                array_pop($ret);
            } else {
                array_push($ret, $segment);
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
        return in_array($function, explode(',', ini_get('disable_functions')));
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
     * @param $array
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

        if ($string[0] == '/' && $string[3] == '/' && in_array(substr($string, 1, 2), $languages_enabled)) {
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

        // fallback to strtotime if DateTime approach failed
        if ($datetime !== false) {
            return $datetime->getTimestamp();
        } else {
            return strtotime($date);
        }
    }

    /**
     * @deprecated Use getDotNotation() method instead
     *
     */
    public static function resolve(array $array, $path, $default = null)
    {
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
     * @param bool   $plusOneTick if true, generates the token for the next tick (the next 12 hours)
     *
     * @return string the nonce string
     */
    private static function generateNonceString($action, $plusOneTick = false)
    {
        $username = '';
        if (isset(Grav::instance()['user'])) {
            $user = Grav::instance()['user'];
            $username = $user->username;
        }

        $token = session_id();
        $i = self::nonceTick();

        if ($plusOneTick) {
            $i++;
        }

        return ($i . '|' . $action . '|' . $username . '|' . $token . '|' . Grav::instance()['config']->get('security.salt'));
    }

    //Added in version 1.0.8 to ensure that existing nonces are not broken.
    private static function generateNonceStringOldStyle($action, $plusOneTick = false)
    {
        if (isset(Grav::instance()['user'])) {
            $user = Grav::instance()['user'];
            $username = $user->username;
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $username .= $_SERVER['REMOTE_ADDR'];
            }
        } else {
            $username = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }
        $token = session_id();
        $i = self::nonceTick();
        if ($plusOneTick) {
            $i++;
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

        return (int)ceil(time() / ($secondsInHalfADay));
    }

    /**
     * Creates a hashed nonce tied to the passed action. Tied to the current user and time. The nonce for a given
     * action is the same for 12 hours.
     *
     * @param string $action      the action the nonce is tied to (e.g. save-user-admin or move-page-homepage)
     * @param bool   $plusOneTick if true, generates the token for the next tick (the next 12 hours)
     *
     * @return string the nonce
     */
    public static function getNonce($action, $plusOneTick = false)
    {
        // Don't regenerate this again if not needed
        if (isset(static::$nonces[$action])) {
            return static::$nonces[$action];
        }
        $nonce = md5(self::generateNonceString($action, $plusOneTick));
        static::$nonces[$action] = $nonce;

        return static::$nonces[$action];
    }

    //Added in version 1.0.8 to ensure that existing nonces are not broken.
    public static function getNonceOldStyle($action, $plusOneTick = false)
    {
        // Don't regenerate this again if not needed
        if (isset(static::$nonces[$action])) {
            return static::$nonces[$action];
        }
        $nonce = md5(self::generateNonceStringOldStyle($action, $plusOneTick));
        static::$nonces[$action] = $nonce;

        return static::$nonces[$action];
    }

    /**
     * Verify the passed nonce for the give action
     *
     * @param string $nonce  the nonce to verify
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
        if ($nonce == self::getNonce($action)) {
            return true;
        }

        //Nonce generated 12-24 hours ago
        $plusOneTick = true;
        if ($nonce == self::getNonce($action, $plusOneTick)) {
            return true;
        }


        //Added in version 1.0.8 to ensure that existing nonces are not broken.
        //Nonce generated 0-12 hours ago
        if ($nonce == self::getNonceOldStyle($action)) {
            return true;
        }

        //Nonce generated 12-24 hours ago
        $plusOneTick = true;
        if ($nonce == self::getNonceOldStyle($action, $plusOneTick)) {
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
        if (is_null($key)) return $array;

        if (isset($array[$key])) return $array[$key];

        foreach (explode('.', $key) as $segment)
        {
            if ( ! is_array($array) ||
                ! array_key_exists($segment, $array))
            {
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
        if (is_null($key)) return $array = $value;

        $keys = explode('.', $key);

        while (count($keys) > 1)
        {
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
}
