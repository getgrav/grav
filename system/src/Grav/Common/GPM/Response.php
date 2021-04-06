<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Exception;
use Grav\Common\Utils;
use Grav\Common\Grav;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use function call_user_func;
use function defined;
use function function_exists;

/**
 * Class Response
 * @package Grav\Common\GPM
 */
class Response
{
    /** @var callable    The callback for the progress, either a function or callback in array notation */
    public static $callback = null;
    /** @var string[] */
    private static $headers = [
        'User-Agent' => 'Grav CMS'
    ];

    /**
     * Makes a request to the URL by using the preferred method
     *
     * @param string $uri URL to call
     * @param array $overrides An array of parameters for both `curl` and `fopen`
     * @param callable|null $callback Either a function or callback in array notation
     * @return string The response of the request
     * @throws TransportExceptionInterface
     */
    public static function get($uri = '', $overrides = [], $callback = null)
    {
        if (empty($uri)) {
            throw new TransportException('missing URI');
        }

        // check if this function is available, if so use it to stop any timeouts
        try {
            if (Utils::functionExists('set_time_limit')) {
                @set_time_limit(0);
            }
        } catch (Exception $e) {
        }

        $config = Grav::instance()['config'];
        $referer = defined('GRAV_CLI') ? 'grav_cli' : Grav::instance()['uri']->rootUrl(true);
        $options = new HttpOptions();

        // Set default Headers
        $options->setHeaders(array_merge([ 'Referer' => $referer ], self::$headers));

        // Disable verify Peer if required
        $verify_peer = $config->get('system.gpm.verify_peer', true);
        if ($verify_peer !== true) {
            $options->verifyPeer($verify_peer);
        }

        // Set proxy url if provided
        $proxy_url = $config->get('system.gpm.proxy_url', false);
        if ($proxy_url) {
            $options->setProxy($proxy_url);
        }

        // Use callback if provided
        if ($callback) {
            self::$callback = $callback;
            $options->setOnProgress([Response::class, 'progress']);
        }

        $preferred_method = $config->get('system.gpm.method', 'auto');

        $settings = array_merge_recursive($options->toArray(), $overrides);

        switch ($preferred_method) {
            case 'curl':
                $client = new CurlHttpClient($settings);
                break;
            case 'fopen':
            case 'native':
                $client = new NativeHttpClient($settings);
                break;
            default:
                $client = HttpClient::create($settings);
        }

        $response = $client->request('GET', $uri);

        return $response->getContent();
    }


    /**
     * Is this a remote file or not
     *
     * @param string $file
     * @return bool
     */
    public static function isRemote($file)
    {
        return (bool) filter_var($file, FILTER_VALIDATE_URL);
    }

    /**
     * Progress normalized for cURL and Fopen
     * Accepts a variable length of arguments passed in by stream method
     *
     * @return void
     */
    public static function progress(int $bytes_transferred, int $filesize, array $info)
    {

        if ($bytes_transferred > 0) {
            $percent = $filesize <= 0 ? 0 : (int)(($bytes_transferred * 100) / $filesize);

            $progress = [
                'code'        => $info['http_code'],
                'filesize'    => $filesize,
                'transferred' => $bytes_transferred,
                'percent'     => $percent < 100 ? $percent : 100
            ];

            if (self::$callback !== null) {
                call_user_func(self::$callback, $progress);
            }
        }
    }
}
