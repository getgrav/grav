<?php

/**
 * @package    Grav\Framework\Uri
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Uri;

/**
 * Class Uri
 * @package Grav\Framework\Uri
 */
class UriPartsFilter
{
    const HOSTNAME_REGEX = '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/u';

    /**
     * @param string $scheme
     * @return string
     * @throws \InvalidArgumentException If the scheme is invalid.
     */
    public static function filterScheme($scheme)
    {
        if (!\is_string($scheme)) {
            throw new \InvalidArgumentException('Uri scheme must be a string');
        }

        return strtolower($scheme);
    }

    /**
     * Filters the user info string.
     *
     * @param string $info The raw user or password.
     * @return string The percent-encoded user or password string.
     * @throws \InvalidArgumentException
     */
    public static function filterUserInfo($info)
    {
        if (!\is_string($info)) {
            throw new \InvalidArgumentException('Uri user info must be a string');
        }

        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=]+|%(?![A-Fa-f0-9]{2}))/u',
            function ($match) {
                return rawurlencode($match[0]);
            },
            $info
        ) ?? '';
    }

    /**
     * @param string $host
     * @return string
     * @throws \InvalidArgumentException If the host is invalid.
     */
    public static function filterHost($host)
    {
        if (!\is_string($host)) {
            throw new \InvalidArgumentException('Uri host must be a string');
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . ']';
        } elseif ($host && preg_match(static::HOSTNAME_REGEX, $host) !== 1) {
            throw new \InvalidArgumentException('Uri host name validation failed');
        }

        return strtolower($host);
    }

    /**
     * Filter Uri port.
     *
     * This method
     *
     * @param int|null $port
     * @return int|null
     * @throws \InvalidArgumentException If the port is invalid.
     */
    public static function filterPort($port = null)
    {
        if (null === $port || (\is_int($port) && ($port >= 0 && $port <= 65535))) {
            return $port;
        }

        throw new \InvalidArgumentException('Uri port must be null or an integer between 0 and 65535');
    }

    /**
     * Filter Uri path.
     *
     * This method percent-encodes all reserved characters in the provided path string. This method
     * will NOT double-encode characters that are already percent-encoded.
     *
     * @param  string $path The raw uri path.
     * @return string       The RFC 3986 percent-encoded uri path.
     * @throws \InvalidArgumentException If the path is invalid.
     * @link   http://www.faqs.org/rfcs/rfc3986.html
     */
    public static function filterPath($path)
    {
        if (!\is_string($path)) {
            throw new \InvalidArgumentException('Uri path must be a string');
        }

        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/u',
            function ($match) {
                return rawurlencode($match[0]);
            },
            $path
        ) ?? '';
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param string $query The raw uri query string.
     * @return string The percent-encoded query string.
     * @throws \InvalidArgumentException If the query is invalid.
     */
    public static function filterQueryOrFragment($query)
    {
        if (!\is_string($query)) {
            throw new \InvalidArgumentException('Uri query string and fragment must be a string');
        }

        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~!\$&\'\(\)\*\+,;=%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/u',
            function ($match) {
                return rawurlencode($match[0]);
            },
            $query
        ) ?? '';
    }
}
