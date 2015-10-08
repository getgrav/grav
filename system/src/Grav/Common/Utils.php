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

    /**
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
     * @return string
     */
    public static function substrToString($haystack, $needle)
    {
        if (static::contains($haystack, $needle)) {
            return substr($haystack, 0, strpos($haystack,$needle));
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
     * @param  int $limit Max number of characters.
     * @param  bool $up_to_break truncate up to breakpoint after char count
     * @param  string $break Break point.
     * @param  string $pad Appended padding to the end of the string.
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
     * @param $string
     * @param int $limit
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
        return Truncator::truncate($text, $length, array('length_in_chars' => true));
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
        return Truncator::truncate($text, $length, array('length_in_chars' => true, 'word_safe' => true));
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
     * @param      $file            the full path to the file to be downloaded
     * @param bool $force_download  as opposed to letting browser choose if to download or render
     */
    public static function download($file, $force_download = true)
    {
        if (file_exists($file)) {
            // fire download event
            self::getGrav()->fireEvent('onBeforeDownload', new Event(['file' => $file]));

            $file_parts = pathinfo($file);
            $filesize = filesize($file);

            // check if this function is available, if so use it to stop any timeouts
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
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
     * @param $extension Extension of file (eg .txt)
     *
     * @return string
     */
    public static function getMimeType($extension)
    {
        $extension = strtolower($extension);

        switch ($extension) {
            case "js":
                return "application/x-javascript";

            case "json":
                return "application/json";

            case "jpg":
            case "jpeg":
            case "jpe":
                return "image/jpg";

            case "png":
            case "gif":
            case "bmp":
            case "tiff":
                return "image/" . $extension;

            case "css":
                return "text/css";

            case "xml":
                return "application/xml";

            case "doc":
            case "docx":
                return "application/msword";

            case "xls":
            case "xlt":
            case "xlm":
            case "xld":
            case "xla":
            case "xlc":
            case "xlw":
            case "xll":
                return "application/vnd.ms-excel";

            case "ppt":
            case "pps":
                return "application/vnd.ms-powerpoint";

            case "rtf":
                return "application/rtf";

            case "pdf":
                return "application/pdf";

            case "html":
            case "htm":
            case "php":
                return "text/html";

            case "txt":
                return "text/plain";

            case "mpeg":
            case "mpg":
            case "mpe":
                return "video/mpeg";

            case "mp3":
                return "audio/mpeg3";

            case "wav":
                return "audio/wav";

            case "aiff":
            case "aif":
                return "audio/aiff";

            case "avi":
                return "video/msvideo";

            case "wmv":
                return "video/x-ms-wmv";

            case "mov":
                return "video/quicktime";

            case "zip":
                return "application/zip";

            case "tar":
                return "application/x-tar";

            case "swf":
                return "application/x-shockwave-flash";

            default:
                return "application/octet-stream";
        }
    }

    /**
     * Normalize path by processing relative `.` and `..` syntax and merging path
     *
     * @param $path
     *
     * @return string
     */
    public static function normalizePath($path)
    {
        $root = ($path[0] === '/') ? '/' : '';

        $segments = explode('/', trim($path, '/'));
        $ret = array();
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

        $timezone_list = array();
        foreach ($offsets as $timezone => $offset) {
            $offset_prefix = $offset < 0 ? '-' : '+';
            $offset_formatted = gmdate('H:i', abs($offset));

            $pretty_offset = "UTC${offset_prefix}${offset_formatted}";

            $timezone_list[$timezone] = "(${pretty_offset}) $timezone";
        }

        return $timezone_list;

    }

    public static function arrayFilterRecursive(Array $source, $fn)
    {
        $result = array();
        foreach ($source as $key => $value)
        {
            if (is_array($value))
            {
                $result[$key] = static::arrayFilterRecursive($value, $fn);
                continue;
            }
            if ($fn($key, $value))
            {
                $result[$key] = $value; // KEEP
                continue;
            }
        }
        return $result;
    }

    public static function pathPrefixedByLangCode($string)
    {
        $languages_enabled = self::getGrav()['config']->get('system.languages.supported', []);

        if ($string[0] == '/' && $string[3] == '/' && in_array(substr($string, 1, 2), $languages_enabled)) {
            return true;
        }

        return false;
    }

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

}
