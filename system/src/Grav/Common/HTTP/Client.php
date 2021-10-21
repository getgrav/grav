<?php

/**
 * @package    Grav\Common\HTTP
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\HTTP;

use Grav\Common\Grav;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client
{
    /** @var callable    The callback for the progress, either a function or callback in array notation */
    public static $callback = null;
    /** @var string[] */
    private static $headers = [
        'User-Agent' => 'Grav CMS'
    ];

    public static function getClient(array $overrides = [], int $connections = 6, callable $callback = null): HttpClientInterface
    {
        $config = Grav::instance()['config'];
        $options = static::getOptions();

        // Use callback if provided
        if ($callback) {
            self::$callback = $callback;
            $options->setOnProgress([Client::class, 'progress']);
        }

        $settings = array_merge($options->toArray(), $overrides);
        $preferred_method = $config->get('system.http.method');
        // Try old GPM setting if value is the same as system default
        if ($preferred_method === 'auto') {
            $preferred_method = $config->get('system.gpm.method', 'auto');
        }

        switch ($preferred_method) {
            case 'curl':
                $client = new CurlHttpClient($settings, $connections);
                break;
            case 'fopen':
            case 'native':
                $client = new NativeHttpClient($settings, $connections);
                break;
            default:
                $client = HttpClient::create($settings, $connections);
        }

        return $client;
    }

    /**
     * Get HTTP Options
     *
     * @return HttpOptions
     */
    public static function getOptions(): HttpOptions
    {
        $config = Grav::instance()['config'];
        $referer = defined('GRAV_CLI') ? 'grav_cli' : Grav::instance()['uri']->rootUrl(true);

        $options = new HttpOptions();

        // Set default Headers
        $options->setHeaders(array_merge([ 'Referer' => $referer ], self::$headers));

        // Disable verify Peer if required
        $verify_peer = $config->get('system.http.verify_peer');
        // Try old GPM setting if value is default
        if ($verify_peer === true) {
            $verify_peer = $config->get('system.gpm.verify_peer', null) ?? $verify_peer;
        }
        $options->verifyPeer($verify_peer);

        // Set verify Host
        $verify_host = $config->get('system.http.verify_host', true);
        $options->verifyHost($verify_host);

        // New setting and must be enabled for Proxy to work
        if ($config->get('system.http.enable_proxy', true)) {
            // Set proxy url if provided
            $proxy_url = $config->get('system.http.proxy_url', $config->get('system.gpm.proxy_url', null));
            if ($proxy_url !== null) {
                $options->setProxy($proxy_url);
            }

            // Certificate
            $proxy_cert = $config->get('system.http.proxy_cert_path', null);
            if ($proxy_cert !== null) {
                $options->setCaPath($proxy_cert);
            }
        }

        return $options;
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
