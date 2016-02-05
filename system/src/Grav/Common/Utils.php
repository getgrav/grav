<?php
namespace Grav\Common;

use DateTime;
use DateTimeZone;
use Grav\Common\Helpers\Truncator;
use RocketTheme\Toolbox\Event\Event;

/**
 * Misc utilities.
 *
 * @package Grav\Common
 */
abstract class Utils
{
    use GravTrait;

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
            'm/d/Y h:i a' => 'm/d/Y h:i (e.g. '.$now->format('m/d/Y h:i a').')',
            'H:i d-m-Y' => 'H:i d-m-Y (e.g. '.$now->format('H:i d-m-Y').')',
            'h:i a m/d/Y' => 'h:i a m/d/Y (e.g. '.$now->format('h:i a m/d/Y').')',
            ];
        $default_format = self::getGrav()['config']->get('system.pages.dateformat.default');
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
     * @param  int    $length
     *
     * @return string
     */
    public static function truncateHtml($text, $length = 100)
    {
        return Truncator::truncate($text, $length, ['length_in_chars' => true]);
    }

    /**
     * Truncate HTML by number of characters in a "word-safe" manor.
     *
     * @param  string $text
     * @param  int    $length
     *
     * @return string
     */
    public static function safeTruncateHtml($text, $length = 100)
    {
        return Truncator::truncate($text, $length, ['length_in_chars' => true, 'word_safe' => true]);
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
     * @param string $file           the full path to the file to be downloaded
     * @param bool   $force_download as opposed to letting browser choose if to download or render
     */
    public static function download($file, $force_download = true)
    {
        if (file_exists($file)) {
            // fire download event
            self::getGrav()->fireEvent('onBeforeDownload', new Event(['file' => $file]));

            $file_parts = pathinfo($file);
            $filesize = filesize($file);

            // check if this function is available, if so use it to stop any timeouts
            try {
                if (!Utils::isFunctionDisabled('set_time_limit') && !ini_get('safe_mode') && function_exists('set_time_limit')) {
                    set_time_limit(0);
                }
            } catch (\Exception $e) {
            }

            ignore_user_abort(false);

            if ($force_download) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename=' . $file_parts['basename']);
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
            } else {
                header("Content-Type: " . Utils::getMimeType($file_parts['extension']));
            }
            header('Content-Length: ' . $filesize);

            // 8kb chunks for now
            $chunk = 8 * 1024;

            $fh = fopen($file, "rb");

            if ($fh === false) {
                return;
            }

            // Repeat reading until EOF
            while (!feof($fh)) {
                echo fread($fh, $chunk);

                ob_flush();  // flush output
                flush();
            }

            exit;
        }
    }

    /**
     * Return the mimetype based on filename
     *
     * @param string $extension Extension of file (eg "txt")
     *
     * @return string
     */
    public static function getMimeType($extension)
    {
        $extension = strtolower($extension);
        $config = self::getGrav()['config']->get('media');

        if (isset($config[$extension])) {
            return $config[$extension]['mime'];
        }

        return 'application/octet-stream';
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
            if (($segment == '.') || empty($segment)) {
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

            $timezone_list[$timezone] = "(${pretty_offset}) $timezone";
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

        $languages_enabled = self::getGrav()['config']->get('system.languages.supported', []);

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
     *
     * @return int the timestamp
     */
    public static function date2timestamp($date)
    {
        $config = self::getGrav()['config'];
        $default_dateformat = $config->get('system.pages.dateformat.default');

        // try to use DateTime and default format
        if ($default_dateformat) {
            $datetime = DateTime::createFromFormat($default_dateformat, $date);
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
     * Get value of an array element using dot notation
     *
     * @param array  $array   the Array to check
     * @param string $path    the dot notation path to check
     * @param mixed  $default a value to be returned if $path is not found in $array
     *
     * @return mixed the value found
     */
    public static function resolve(array $array, $path, $default = null)
    {
        $current = $array;
        $p = strtok($path, '.');

        while ($p !== false) {
            if (!isset($current[$p])) {
                return $default;
            }
            $current = $current[$p];
            $p = strtok('.');
        }

        return $current;
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
        if (isset(self::getGrav()['user'])) {
            $user = self::getGrav()['user'];
            $username = $user->username;
        }

        $token = session_id();
        $i = self::nonceTick();

        if ($plusOneTick) {
            $i++;
        }

        return ($i . '|' . $action . '|' . $username . '|' . $token . '|' . self::getGrav()['config']->get('security.salt'));
    }

    //Added in version 1.0.8 to ensure that existing nonces are not broken.
    private static function generateNonceStringOldStyle($action, $plusOneTick = false)
    {
        if (isset(self::getGrav()['user'])) {
            $user = self::getGrav()['user'];
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

        return ($i . '|' . $action . '|' . $username . '|' . $token . '|' . self::getGrav()['config']->get('security.salt'));
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
}
