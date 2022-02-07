<?php

/**
 * @package    Grav\Common\HTTP
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\HTTP;

use Exception;
use Grav\Common\Utils;
use Grav\Common\Grav;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function call_user_func;
use function defined;

/**
 * Class Response
 * @package Grav\Common\GPM
 */
class Response
{
    /**
     * Backwards compatible helper method
     *
     * @param string $uri
     * @param array $overrides
     * @param callable|null $callback
     * @return string
     * @throws TransportExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|ClientExceptionInterface
     */
    public static function get(string $uri = '', array $overrides = [], callable $callback = null): string
    {
        $response = static::request('GET', $uri, $overrides, $callback);
        return $response->getContent();
    }


    /**
     * Makes a request to the URL by using the preferred method
     *
     * @param string $method method to call such as GET, PUT, etc
     * @param string $uri URL to call
     * @param array $overrides An array of parameters for both `curl` and `fopen`
     * @param callable|null $callback Either a function or callback in array notation
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function request(string $method, string $uri, array $overrides = [], callable $callback = null): ResponseInterface
    {
        if (empty($method)) {
            throw new TransportException('missing method (GET, PUT, etc.)');
        }

        if (empty($uri)) {
            throw new TransportException('missing URI');
        }

        // check if this function is available, if so use it to stop any timeouts
        try {
            if (Utils::functionExists('set_time_limit')) {
                @set_time_limit(0);
            }
        } catch (Exception $e) {}

        $client = Client::getClient($overrides, 6, $callback);

        return $client->request($method, $uri);
    }


    /**
     * Is this a remote file or not
     *
     * @param string $file
     * @return bool
     */
    public static function isRemote($file): bool
    {
        return (bool) filter_var($file, FILTER_VALIDATE_URL);
    }


}
