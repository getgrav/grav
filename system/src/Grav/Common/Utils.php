<?php
namespace Grav\Common;

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
     * List of file mime types , this is not all mime types
     * if you need all mime types change this
     *
     * @access public
     * @var array
     */
    public static $mimeTypes = array(
        // (Plain) text
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'es' => 'application/ecmascript',
        'rss' => 'application/rss+xml',

        // Images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // Archives
        'zip' => 'application/zip',
        'tar' => 'application/x-tar',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',
        'tgz' => 'application/x-tar',
        'bz2' => 'application/x-bzip2',
        'jar' => 'application/java-archive',

        // Audio
        'aiff' => 'audio/aiff',
        'aif' => 'audio/aiff',
        'flv' => 'audio/x-flv',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'avi' => 'audio/msvideo',
        'wmv' => 'audio/x-ms-wmv',
        'wma' => 'audio/x-ms-wma',
        '3gp' => 'audio/tgpp',
        'midi' => 'audio/x-midi',
        'mp4a' => 'audio/mp4',
        'ra' => 'audio/x-pn-realaudio',
        'ram' => 'audio/x-pn-realaudio',

        // Video
        'qt'  => 'video/quicktime',
        'mov' => 'video/quicktime',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        'wmv' => 'video/x-ms-wmv',
        'ogv' => 'video/ogg',
        'webm' => 'video/webm',
        'm4v' => 'video/x-m4v',
        'mp4' => 'application/mp4',

        // Adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai'  => 'application/postscript',
        'eps' => 'application/postscript',
        'ps'  => 'application/postscript',

        // MS office
        'doc' => 'application/msword',
        'docx' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'xlt' => 'application/vnd.ms-excel',
        'xlm' => 'application/vnd.ms-excel',
        'xld' => 'application/vnd.ms-excel',
        'xla' => 'application/vnd.ms-excel',
        'xlc' => 'application/vnd.ms-excel',
        'xlw' => 'application/vnd.ms-excel',
        'xll' => 'application/vnd.ms-excel',
        'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',
        'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pps' => 'application/vnd.ms-powerpoint',
        'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'ppt'  => 'application/vnd.ms-powerpointtd',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // Open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',

        // Other
        'rm'  => 'application/vnd.rn-realmedia',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
    );

    /**
     * @param  string  $haystack
     * @param  string  $needle
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    /**
     * @param  string  $haystack
     * @param  string  $needle
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * @param  string  $haystack
     * @param  string  $needle
     * @return bool
     */
    public static function contains($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

    /**
     * Merge two objects into one.
     *
     * @param  object $obj1
     * @param  object $obj2
     * @return object
     */
    public static function mergeObjects($obj1, $obj2)
    {
        return (object) array_merge((array) $obj1, (array) $obj2);
    }

    /**
     * Truncate HTML by text length.
     *
     * @param  string $text
     * @param  int    $length
     * @param  string $ending
     * @param  bool   $exact
     * @param  bool   $considerHtml
     * @return string
     */
    public static function truncateHtml($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true)
    {
        $open_tags = array();
        if ($considerHtml) {
            // if the plain text is shorter than the maximum length, return the whole text
            if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }
            // splits all html-tags to scanable lines
            preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
            $total_length = strlen($ending);
            $truncate = '';
            foreach ($lines as $line_matchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($line_matchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash
                    if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                        // do nothing
                    // if tag is a closing tag
                    } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                        // delete tag from $open_tags list
                        $pos = array_search($tag_matchings[1], $open_tags);
                        if ($pos !== false) {
                            unset($open_tags[$pos]);
                        }
                    // if tag is an opening tag
                    } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                        // add tag to the beginning of $open_tags list
                        array_unshift($open_tags, strtolower($tag_matchings[1]));
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $line_matchings[1];
                }
                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
                if ($total_length+$content_length> $length) {
                    // the number of characters which are left
                    $left = $length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                        // calculate the real length of all entities in the legal range
                        foreach ($entities[0] as $entity) {
                            if ($entity[1]+1-$entities_length <= $left) {
                                $left--;
                                $entities_length += strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                    // maximum length is reached, so get off the loop
                    break;
                } else {
                    $truncate .= $line_matchings[2];
                    $total_length += $content_length;
                }
                // if the maximum length is reached, get off the loop
                if ($total_length >= $length) {
                    break;
                }
            }
        } else {
            if (strlen($text) <= $length) {
                return $text;
            } else {
                $truncate = substr($text, 0, $length - strlen($ending));
            }
        }
        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            // ...search the last occurance of a space...
            $spacepos = strrpos($truncate, ' ');
            if (isset($spacepos)) {
                // ...and cut the text in this position
                $truncate = substr($truncate, 0, $spacepos);
            }
        }
        // add the defined ending to the text
        $truncate .= $ending;
        if ($considerHtml) {
            // close all unclosed html-tags
            foreach ($open_tags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }
        return $truncate;
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
     * @param  string  $filename            The full path to the file
     *                                      to be downloaded
     * @param  string  $options['filename'] Filename to download as
     * @param  integer $options['limit']    Download speed in KB/s
     * @param  boolean $options['force_download'] Set to TRUE to force
     *                                      download file and FALSE to
     *                                      stream file to the browser
     * @param  bool    $options['exit']     Set to exit the program after
     *                                      sending the file to the browser.
     *
     * @return bool                         Return status.
     */
    public static function download($filename, $options = [])
    {
        $defaults = [
            'filename' => '',
            'limit' => 0,
            'force_download' => true,
            'exit' => true,
        ];
        $options += $defaults;

        /**
         * Checks
         */
        if (connection_status() != 0) {
            return false;
        }

        // File is not a file or doesn't exists
        if(!is_file($filename) || !file_exists($filename) || !is_readable($filename)) {
            // Issue HTTP/1.1 404 Not Found
            http_response_code(404);
            exit();
        }

        // Flush buffer(s) before sending file to browser
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Fire download event
        self::getGrav()->fireEvent('onBeforeDownload', new Event(['file' => $filename]));

        /**
         * Setup
         */

        // Some stats about file
        $file_parts = pathinfo($filename);
        $filemtime = filemtime($filename);
        $filesize = filesize($filename);
        $mimeType = self::getMimeType($filename);

        // Set filename as shown by the browser
        $basename = strlen($options['filename']) ? $options['filename'] : $file_parts['basename'];

        // Turn off compression on the server
        set_time_limit(0);
        ignore_user_abort(false);
        apache_setenv('no-gzip', 1);
        ini_set('output_buffering', 0);
        ini_set('zlib.output_compression', 0);

        /**
         * Headers
         */

        // Set the headers
        header('Content-Description: File Transfer');
        header("Content-Type: $mimeType");

        // Prevent Internet Explorer from MIME-sniffing the content-type:
        header('X-Content-Type-Options: nosniff');

        // Set Last-Modified and ETag headers.
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $filemtime) . ' GMT');
        header('ETag: ' . implode('-', [dechex(fileinode($filename)), dechex($filemtime), dechex($filesize)]));

        // Tell client that we accept byte ranges.
        header('Accept-Ranges: bytes');

        // Prevent caching
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: public, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        // Set appropriate headers for attachment or streamed file
        if ($options['force_download']) {
            $fname = $basename;
            // Workaround for IE filename bug with multiple periods / multiple
            // dots in filename that adds square brackets to filename - e.g.
            // setup.abc.exe becomes setup[1].abc.exe + urlencode filename
            if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') || strpos('MSIE', $_SERVER['HTTP_REFERER'])) {
                $fname = rawurlencode(preg_replace('/\./', '%2e', $basename, substr_count($basename, '.') - 1));
            }

            // Set content disposition
            header('Content-Disposition: attachment; filename="' . $fname . '"');
        } else {
            header('Content-Disposition: inline');
        }

        /**
         * Byte range handling (support all flavors of byte ranges)
         */

        $range = false;
        $partial = false;
        $end_file = $filesize - 1;

        // Get the 'Range' header if one was sent
        if (isset($_SERVER['HTTP_RANGE'])) {
            // IIS/Some Apache versions
            $range = strtolower($_SERVER['HTTP_RANGE']);
        } elseif ($apache = apache_request_headers()) {
            // Try Apache again
            $headers = [];
            foreach ($apache as $header => $value) {
                $header = strtolower($header);
                if ($header == 'range') {
                    $range = strtolower($value);
                }
            }
        }

        /**
         * Partial requests
         *
         * Multiple ranges could be specified at the same time
         *     http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
         */
        if ($range) {
            $partial = true;

            // Figure out download piece from range (if set)
            if (substr($range, 0, 6) !== 'bytes=') {
                // Issue HTTP/1.1 400 Invalid Request
                http_response_code(400);
                exit();
            }

            preg_match_all('~
                (?P<start>\d+)?         # Number of the first byte returned
                -                       # Hyphen (always present)
                (?P<end>\d+)?           # Number of the last byte returned
                (?:,|$)                 # Either a comma or end of string
            ~ix', $range, $ranges, PREG_SET_ORDER);

            foreach ($ranges as $key => $range) {
                if (strlen($range['start']) == 0) {
                    unset($range['start']);
                }

                if (!isset($range['start']) && !isset($range['end'])) {
                    // No range given; delete and continue
                    unset($ranges[$key]);
                    continue;
                }

                // Get start and end $range of range
                $from = isset($range['start']) ? (int) $range['start'] : max(0, $end_file);
                $to = isset($range['end']) ? min((int) $range['end'], $end_file) : $end_file;

                if (isset($range['end']) && !isset($range['start'])) {
                    $from = $end_file - $to;
                    $to = $end_file;
                }

                // Remove range, if range is not valid
                if ($from >= $to) {
                    unset($ranges[$key]);
                } else {
                    // Reformat range (drop match items)
                    $ranges[$key] = ['from' => $from, 'to' => $to];
                }
            }

            // Check for overlapping ranges.
            $cranges = $ranges;
            foreach ($ranges as $key => $range) {
                foreach ($cranges as $ckey => $crange) {
                    // Don't compare a range to itself.
                    if ($key == $ckey) {
                        unset($cranges[$ckey]);
                    } else {
                        $overlap = false;

                        // The beginning of this range is in another range.
                        if ($range['from'] >= $crange['from'] && $range['from'] <= $crange['to'] + 1) {
                            $ranges[$key]['from'] = $range['from'] = $crange['from'];
                            $overlap = true;
                        }

                        // The end of this range is in another range.
                        if ($range['to'] <= $crange['to'] && $range['to'] >= $crange['from'] - 1) {
                            $ranges[$key]['to'] = $range['to'] = $crange['to'];
                            $overlap = true;
                        }

                        if ($overlap) {
                            unset($ranges[$ckey], $cranges[$ckey]);
                        }
                    }
                }
            }
        }

        // Handle Partial requests
        $protocol = $_SERVER['SERVER_PROTOCOL'];
        if ($partial) {
            header("$protocol 206 Partial Content");
            header('Status: 206 Partial Content');

            if (count($ranges) == 1) {
                $range = array_shift($ranges);
                $size = $range['to'] - $range['from'] + 1;

                header("Content-Range: bytes {$range['from']}-{$range['to']}/$filesize");
                header("Content-Length: $size");

                self::resumableDownload($filename, $size, $range['from'], $options['limit']);
            } else {
                $boundary = md5(rand());
                header("Content-Type: multipart/x-byteranges; boundary=$boundary");

                foreach ($ranges as $range) {
                    $size = $range['to'] - $range['from'] + 1;

                    print("\r\n--$boundary\r\nContent-Type: $mimeType\r\nContent-Range: bytes {$range['from']}-{$range['to']}/$filesize\r\n\r\n");
                    self::resumableDownload($filename, $size, $range['from'], $options['limit']);
                    print("\r\n");
                }
                print("\r\n--$boundary--\r\n");
            }
        } else {
            $partial = false;
            if ((count($ranges) > 0) && !isset($_SERVER['If-Range']) ) {
                header("$protocol 416 Requested Range Not Satisfiable");
                header('Content-Range: *');
            }
        }

        // Start download (without byte ranges)
        if (!partial || (count($ranges) == 0)) {
            header("Content-Length: $filesize");
            header('Connection: close');

            // Fire download event
            self::getGrav()->fireEvent('onDownloadStart', new Event(['file' => $filename]));

            self::resumableDownload($filename, $filesize, 0, $options['limit']);
        }

        // Fire download event depending on status
        $status = (bool) ((connection_status() == 0) && !connection_aborted());
        $eventName = $status ? 'onDownloadComplete' : 'onDownloadError';
        self::getGrav()->fireEvent($eventName, new Event(['file' => $filename]));

        // Exit?
        if ($options['exit']) {
            exit();
        }

        // Return status
        return $status;
    }

    /**
     * Transfer a byte-range in chunks to save memory usage.
     *
     * @param  string  $filename  The filename to a valid file.
     * @param  integer $length    Number of bytes to transfer
     * @param  integer $byte      Starting byte for this range
     * @param  integer $limit     Download speed in KB/s
     */
    protected static function resumableDownload($filename, $length = 0, $byte = 0, $limit = 0) {

        // Get download options
        $options = self::getGrav()['config']->get('system.download');
        $options['limit'] = $limit ? $limit : $options['limit'];

        // Open file
        if (false === ($handle = fopen($filename, 'rb'))) {
            return;
        }

        // Set script execution time tobe unlimited
        set_time_limit(0);

         // Move file pointer to starting byte.
        fseek($handle, $byte);

        /*
         * The following code attempts to balance the needs of:
         *
         *     - large files don't overly degrade server performance
         *       (max flush once per second)
         *     - download rate limits can be as low as 1 KB/s
         *     - browsers with poor connections don't time out easily
         */

        // How many bytes should be read per second
        $bufferSize = $options['chunk_size'] * 1024;

        // By default no sleep time after each flush
        $sleepTime = 0;

        // Set configurations for the requested speed limit
        if ($options['limit'] > 0) {
            $ReadsPerSecond = 1;
            if ($options['balance'] > 0) {
                // How many buffer flushes per second
                $ReadsPerSecond = max(round(sqrt($options['limit'] / $options['balance'])), 1);
            }

            // How many bytes should be read per second (max. 8M)
            $bufferSize = min(round($options['limit'] * 1024 / $ReadsPerSecond), 8 * 1024 * 1024);

            // How long one buffering flush takes by micro second
            $minSleepTime = 100;

            // Calculate sleep micro time after each flush
            $sleepTime = max(round(1000000 / $ReadsPerSecond - $ReadsPerSecond * $minSleepTime), $minSleepTime);
        }

        if ($options['debug']) {
            self::getGrav()['debugger']->addMessage(sprintf('Start download "%s" (bufferSize=%s [byte], readsPerSecond=%s [1/s], sleepTime=%s [ms])', $filename, $bufferSize, $ReadsPerSecond, $sleepTime));
        }

        // Start download
        $length = ($length > 0) ? $length : filesize($filename);
        while (!(connection_aborted() || connection_status() == 1) && ($length > 0) ) {
            // Transfer chunk of data (note: fread max is 8M)
            $buffer = min($bufferSize, $length);
            echo fread($handle, $buffer);

            // Add to downloaded
            $length -= $buffer;

            // Send to buffer
            ob_flush();
            flush();

            // Limit the output rate (give the web server a rest)
            if ($sleepTime) {
                usleep($sleepTime);
            }
        }

        // Restore default script max execution time
        set_time_limit(ini_get('max_execution_time'));

        // Successfully downloaded file
        fclose($handle);
    }

    /**
     * Return the mimetype based on filename
     *
     * @param  string $filename The filename to gt the mime type for.
     * @param  string $default  The default mime type to be returned in
     *                          case no mime type was matched.
     *
     * @return string           The mime type of $filename.
     */
    public static function getMimeType($filename, $default = 'application/octet-stream')
    {
        $filename = strtolower($filename);

        // Check if deprecated mime_content_type() exists
        if(function_exists('mime_content_type')) {
            $mimeType = mime_content_type($filename);

        // Check if PECL is installed
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimeType = finfo_file($finfo, $filename);
            finfo_close($finfo);
        }

        // Mime type was found
        if (!empty($mimeType)) {
            return $mimeType;
        }

        // Fallback to array of mime types; extract extension
        $extension = substr(strrchr($filename, "."), 1);

        // Lookup mime type
        if (isset(self::$mimeTypes[$extension]) ) {
            return self::$mimeTypes[$extension];
        }

        // Unknown mime type; return default
        return $default;
    }

    /**
     * Normalize path by processing relative `.` and `..` syntax and merging path
     *
     * @param $path
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
        foreach( $offsets as $timezone => $offset )
        {
            $offset_prefix = $offset < 0 ? '-' : '+';
            $offset_formatted = gmdate( 'H:i', abs($offset) );

            $pretty_offset = "UTC${offset_prefix}${offset_formatted}";

            $timezone_list[$timezone] = "(${pretty_offset}) $timezone";
        }

        return $timezone_list;

    }
}
