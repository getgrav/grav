<?php
/**
 * @package    Grav.Common.GPM
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\Utils;
use Grav\Common\Grav;

class Response
{
    /**
     * The callback for the progress
     *
     * @var callable    Either a function or callback in array notation
     */
    public static $callback = null;

    /**
     * Which method to use for HTTP calls, can be 'curl', 'fopen' or 'auto'. Auto is default and fopen is the preferred method
     *
     * @var string
     */
    private static $method = 'auto';

    /**
     * Default parameters for `curl` and `fopen`
     *
     * @var array
     */
    private static $defaults = [

        'curl'  => [
            CURLOPT_REFERER        => 'Grav GPM',
            CURLOPT_USERAGENT      => 'Grav GPM',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HEADER         => false,
            //CURLOPT_SSL_VERIFYPEER => true, // this is set in the constructor since it's a setting
            /**
             * Example of callback parameters from within your own class
             */
            //CURLOPT_NOPROGRESS     => false,
            //CURLOPT_PROGRESSFUNCTION => [$this, 'progress']
        ],
        'fopen' => [
            'method'           => 'GET',
            'user_agent'       => 'Grav GPM',
            'max_redirects'    => 5,
            'follow_location'  => 1,
            'timeout'          => 15,
            /* // this is set in the constructor since it's a setting
            'ssl'              => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
            */
            /**
             * Example of callback parameters from within your own class
             */
            //'notification' => [$this, 'progress']
        ]
    ];

    /**
     * Sets the preferred method to use for making HTTP calls.
     *
     * @param string $method Default is `auto`
     *
     * @return Response
     */
    public static function setMethod($method = 'auto')
    {
        if (!in_array($method, ['auto', 'curl', 'fopen'])) {
            $method = 'auto';
        }

        self::$method = $method;

        return new self();
    }

    /**
     * Makes a request to the URL by using the preferred method
     *
     * @param  string   $uri      URL to call
     * @param  array    $options  An array of parameters for both `curl` and `fopen`
     * @param  callable $callback Either a function or callback in array notation
     *
     * @return string The response of the request
     */
    public static function get($uri = '', $options = [], $callback = null)
    {
        if (!self::isCurlAvailable() && !self::isFopenAvailable()) {
            throw new \RuntimeException('Could not start an HTTP request. `allow_url_open` is disabled and `cURL` is not available');
        }

        // check if this function is available, if so use it to stop any timeouts
        try {
            if (!Utils::isFunctionDisabled('set_time_limit') && !ini_get('safe_mode') && function_exists('set_time_limit')) {
                set_time_limit(0);
            }
        } catch (\Exception $e) {
        }

        $config = Grav::instance()['config'];
        $overrides = [];

        // SSL Verify Peer and Proxy Setting
        $settings = [
            'method'      => $config->get('system.gpm.method', self::$method),
            'verify_peer' => $config->get('system.gpm.verify_peer', true),
            // `system.proxy_url` is for fallback
            // introduced with 1.1.0-beta.1 probably safe to remove at some point
            'proxy_url'   => $config->get('system.gpm.proxy_url', $config->get('system.proxy_url', false)),
        ];

        if (!$settings['verify_peer']) {
            $overrides = array_replace_recursive([], $overrides, [
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => $settings['verify_peer']
                ],
                'fopen' => [
                    'ssl' => [
                        'verify_peer' => $settings['verify_peer'],
                        'verify_peer_name' => $settings['verify_peer'],
                    ]
                ]
            ]);
        }

        // Proxy Setting
        if ($settings['proxy_url']) {
            $proxy = parse_url($settings['proxy_url']);
            $fopen_proxy = ($proxy['scheme'] ?: 'http') . '://' . $proxy['host'] . (isset($proxy['port']) ? ':' . $proxy['port'] : '');

            $overrides = array_replace_recursive([], $overrides, [
                'curl' => [
                    CURLOPT_PROXY => $proxy['host'],
                    CURLOPT_PROXYTYPE => 'HTTP'
                ],
                'fopen' => [
                    'proxy'           => $fopen_proxy,
                    'request_fulluri' => true
                ]
            ]);

            if (isset($proxy['port'])) {
                $overrides['curl'][CURLOPT_PROXYPORT] = $proxy['port'];
            }

            if (isset($proxy['user']) && isset($proxy['pass'])) {
                $fopen_auth = $auth = base64_encode($proxy['user'] . ':' . $proxy['pass']);
                $overrides['curl'][CURLOPT_PROXYUSERPWD] = $proxy['user'] . ':' . $proxy['pass'];
                $overrides['fopen']['header'] = "Proxy-Authorization: Basic $fopen_auth";
            }
        }

        $options = array_replace_recursive(self::$defaults, $options, $overrides);
        $method  = 'get' . ucfirst(strtolower($settings['method']));

        self::$callback = $callback;
        return static::$method($uri, $options, $callback);
    }

    /**
     * Checks if cURL is available
     *
     * @return boolean
     */
    public static function isCurlAvailable()
    {
        return function_exists('curl_version');
    }

    /**
     * Checks if the remote fopen request is enabled in PHP
     *
     * @return boolean
     */
    public static function isFopenAvailable()
    {
        return preg_match('/1|yes|on|true/i', ini_get('allow_url_fopen'));
    }

    /**
     * Progress normalized for cURL and Fopen
     * Accepts a vsariable length of arguments passed in by stream method
     *
     * @return array Normalized array with useful data.
     *               Format: ['code' => int|false, 'filesize' => bytes, 'transferred' => bytes, 'percent' => int]
     */
    public static function progress()
    {
        static $filesize = null;

        $args           = func_get_args();
        $isCurlResource = is_resource($args[0]) && get_resource_type($args[0]) == 'curl';

        $notification_code = !$isCurlResource ? $args[0] : false;
        $bytes_transferred = $isCurlResource ? $args[2] : $args[4];

        if ($isCurlResource) {
            $filesize = $args[1];
        } elseif ($notification_code == STREAM_NOTIFY_FILE_SIZE_IS) {
            $filesize = $args[5];
        }

        if ($bytes_transferred > 0) {
            if ($notification_code == STREAM_NOTIFY_PROGRESS | STREAM_NOTIFY_COMPLETED || $isCurlResource) {

                $progress = [
                    'code'        => $notification_code,
                    'filesize'    => $filesize,
                    'transferred' => $bytes_transferred,
                    'percent'     => $filesize <= 0 ? '-' : round(($bytes_transferred * 100) / $filesize, 1)
                ];

                if (self::$callback !== null) {
                    call_user_func_array(self::$callback, [$progress]);
                }
            }
        }
    }

    /**
     * Automatically picks the preferred method
     *
     * @return string The response of the request
     */
    private static function getAuto()
    {
        if (!ini_get('open_basedir') && self::isFopenAvailable()) {
            return self::getFopen(func_get_args());
        }

        if (self::isCurlAvailable()) {
            return self::getCurl(func_get_args());
        }
    }

    /**
     * Starts a HTTP request via fopen
     *
     * @return string The response of the request
     */
    private static function getFopen()
    {
        if (count($args = func_get_args()) == 1) {
            $args = $args[0];
        }

        $uri      = $args[0];
        $options  = $args[1];
        $callback = $args[2];

        if ($callback) {
            $options['fopen']['notification'] = ['self', 'progress'];
        }

        if (isset($options['fopen']['ssl'])) {
            $ssl = $options['fopen']['ssl'];
            unset($options['fopen']['ssl']);

            $stream  = stream_context_create([
                'http' => $options['fopen'],
                'ssl' => $ssl
            ], $options['fopen']);
        } else {
            $stream  = stream_context_create(['http' => $options['fopen']], $options['fopen']);
        }


        $content = @file_get_contents($uri, false, $stream);

        if ($content === false) {
            $code = null;
            if (isset($http_response_header)) {
                $code = explode(' ', $http_response_header[0])[1];
            }

            switch ($code) {
                case '404':
                    throw new \RuntimeException("Page not found");
                case '401':
                    throw new \RuntimeException("Invalid LICENSE");
                default:
                    throw new \RuntimeException("Error while trying to download '$uri'\n");
            }
        }

        return $content;
    }

    /**
     * Starts a HTTP request via cURL
     *
     * @return string The response of the request
     */
    private static function getCurl()
    {
        $args = func_get_args();
        $args = count($args) > 1 ? $args : array_shift($args);

        $uri      = $args[0];
        $options  = $args[1];
        $callback = $args[2];

        $ch = curl_init($uri);

        $response = static::curlExecFollow($ch, $options, $callback);
        $errno = curl_errno($ch);

        if ($errno) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error_message = curl_strerror($errno) . "\n" . curl_error($ch);

            switch ($code) {
                case '404':
                    throw new \RuntimeException("Page not found");
                case '401':
                    throw new \RuntimeException("Invalid LICENSE");
                default:
                    throw new \RuntimeException("Error while trying to download '$uri'\nMessage: $error_message");
            }
        }

        curl_close($ch);

        return $response;
    }

    /**
     * @param $ch
     * @param $options
     * @param $callback
     *
     * @return bool|mixed
     */
    private static function curlExecFollow($ch, $options, $callback)
    {
        if ($callback) {
            curl_setopt_array(
                $ch,
                [
                    CURLOPT_NOPROGRESS       => false,
                    CURLOPT_PROGRESSFUNCTION => ['self', 'progress']
                ]
            );
        }

        // no open_basedir set, we can proceed normally
        if (!ini_get('open_basedir')) {
            curl_setopt_array($ch, $options['curl']);
            return curl_exec($ch);
        }

        $max_redirects = isset($options['curl'][CURLOPT_MAXREDIRS]) ? $options['curl'][CURLOPT_MAXREDIRS] : 3;
        $options['curl'][CURLOPT_FOLLOWLOCATION] = false;

        // open_basedir set but no redirects to follow, we can disable followlocation and proceed normally
        curl_setopt_array($ch, $options['curl']);
        if ($max_redirects <= 0) {
            return curl_exec($ch);
        }

        $uri = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $rch = curl_copy_handle($ch);

        curl_setopt($rch, CURLOPT_HEADER, true);
        curl_setopt($rch, CURLOPT_NOBODY, true);
        curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
        curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);

        do {
            curl_setopt($rch, CURLOPT_URL, $uri);
            $header = curl_exec($rch);

            if (curl_errno($rch)) {
                $code = 0;
            } else {
                $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                if ($code == 301 || $code == 302) {
                    preg_match('/Location:(.*?)\n/', $header, $matches);
                    $uri = trim(array_pop($matches));
                } else {
                    $code = 0;
                }
            }
        } while ($code && --$max_redirects);

        curl_close($rch);

        if (!$max_redirects) {
            if ($max_redirects === null) {
                trigger_error('Too many redirects. When following redirects, libcurl hit the maximum amount.', E_USER_WARNING);
            }

            return false;
        }

        curl_setopt($ch, CURLOPT_URL, $uri);

        return curl_exec($ch);
    }
}
