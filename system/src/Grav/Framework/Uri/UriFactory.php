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
        $path = rawurldecode(parse_url('http://example.com' . $request_uri, PHP_URL_PATH));

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
     * Advanced `parse_url()` method.
     *
     * @param string $uri
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function parseUrl($uri)
    {
        if (!is_string($uri)) {
            throw new \InvalidArgumentException('Uri must be a string');
        }

        // Set Uri parts.
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new \InvalidArgumentException('Malformed URI: ' . $uri);
        }

        return $parts;
    }
}
