<?php
/**
 * @package    Grav\Framework\Uri
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Uri;

/**
 * Class Uri
 * @package Grav\Framework\Uri
 */
class UriFactory
{
    /**
     * @param array $env
     * @return Uri
     * @throws \InvalidArgumentException
     */
    public static function createFromEnvironment(array $env)
    {
        return new Uri(static::parseUrlFromEnvironment($env));
    }

    /**
     * @param string $uri
     * @return Uri
     * @throws \InvalidArgumentException
     */
    public static function createFromString($uri)
    {
        return new Uri(static::parseUrl($uri));
    }

    /**
     * Creates a URI from a array of `parse_url()` components.
     *
     * @param array $parts
     * @return Uri
     * @throws \InvalidArgumentException
     */
    public static function createFromParts(array $parts)
    {
        return new Uri($parts);
    }

    /**
     * @param array $env
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function parseUrlFromEnvironment(array $env)
    {
        // Build scheme.
        if (isset($env['REQUEST_SCHEME'])) {
            $scheme = strtolower($env['REQUEST_SCHEME']);
        } else {
            $https = isset($env['HTTPS']) ? $env['HTTPS'] : '';
            $scheme = (empty($https) || strtolower($https) === 'off') ? 'http' : 'https';
        }

        // Build user and password.
        $user = isset($env['PHP_AUTH_USER']) ? $env['PHP_AUTH_USER'] : '';
        $pass = isset($env['PHP_AUTH_PW']) ? $env['PHP_AUTH_PW'] : '';

        // Build host.
        $host = 'localhost';
        if (isset($env['HTTP_HOST'])) {
            $host = $env['HTTP_HOST'];
        } elseif (isset($env['SERVER_NAME'])) {
            $host = $env['SERVER_NAME'];
        }
        // Remove port from HTTP_HOST generated $hostname
        $host = explode(':', $host)[0];

        // Build port.
        $port = isset($env['SERVER_PORT']) ? (int)$env['SERVER_PORT'] : null;

        // Build path.
        $request_uri = isset($env['REQUEST_URI']) ? $env['REQUEST_URI'] : '';
        $path = parse_url('http://example.com' . $request_uri, PHP_URL_PATH);

        // Build query string.
        $query = isset($env['QUERY_STRING']) ? $env['QUERY_STRING'] : '';
        if ($query === '') {
            $query = parse_url('http://example.com' . $request_uri, PHP_URL_QUERY);
        }

        // Support ngnix routes.
        if (strpos($query, '_url=') === 0) {
            parse_str($query, $q);
            unset($q['_url']);
            $query = http_build_query($q);
        }

        return [
            'scheme' => $scheme,
            'user' => $user,
            'pass' => $pass,
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $query
        ];
    }

    /**
     * UTF-8 aware parse_url() implementation.
     *
     * @param string $url
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function parseUrl($url)
    {
        if (!is_string($url)) {
            throw new \InvalidArgumentException('URL must be a string');
        }

        $encodedUrl = preg_replace_callback(
            '%[^:/@?&=#]+%u',
            function ($matches) { return rawurlencode($matches[0]); },
            $url
        );

        $parts = parse_url($encodedUrl);
        if ($parts === false) {
            throw new \InvalidArgumentException('Malformed URL: ' . $encodedUrl);
        }

        return $parts;
    }

    /**
     * Parse query string and return it as an array.
     *
     * @param string $query
     * @return mixed
     */
    public static function parseQuery($query)
    {
        parse_str($query, $params);

        return $params;
    }

    /**
     * Build query string from variables.
     *
     * @param array $params
     * @return string
     */
    public static function buildQuery(array $params)
    {
        return $params ? http_build_query($params,  null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986) : '';
    }
}
