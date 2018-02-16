<?php
/**
 * @package    Grav\Framework\Uri
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Uri;

use Psr\Http\Message\UriInterface;

/**
 * Class Uri
 * @package Grav\Framework\Uri
 */
class UriHelper
{
    /** @var array List of default ports for the supported schemes. */
    private static $defaultPorts = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'telnet' => 23,
    ];

    private static $replaceQuery = ['=' => '%3D', '&' => '%26'];

    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `Psr\Http\Message\UriInterface::getPort` may return null or the standard port. This method can be used
     * independently of the implementation.
     *
     * @param UriInterface $uri
     * @return bool
     */
    public static function isDefaultPort(UriInterface $uri)
    {
        $port = $uri->getPort();
        $scheme = $uri->getScheme();

        return $port === null || (isset(static::$defaultPorts[$scheme]) && $port === static::$defaultPorts[$scheme]);
    }

    /**
     * Whether the URI is absolute, i.e. it has a scheme.
     *
     * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
     * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
     * to another URI, the base URI. Relative references can be divided into several forms:
     * - network-path references, e.g. '//example.com/path'
     * - absolute-path references, e.g. '/path'
     * - relative-path references, e.g. 'subpath'
     *
     * @param UriInterface $uri
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4
     */
    public static function isAbsolute(UriInterface $uri)
    {
        return $uri->getScheme() !== '';
    }

    /**
     * Whether the URI is a network-path reference.
     *
     * A relative reference that begins with two slash characters is termed an network-path reference.
     *
     * @param UriInterface $uri
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isNetworkPathReference(UriInterface $uri)
    {
        return $uri->getScheme() === '' && $uri->getAuthority() !== '';
    }

    /**
     * Whether the URI is a absolute-path reference.
     *
     * A relative reference that begins with a single slash character is termed an absolute-path reference.
     *
     * @param UriInterface $uri
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isAbsolutePathReference(UriInterface $uri)
    {
        $path = $uri->getPath();

        return $uri->getScheme() === '' && $uri->getAuthority() === '' && isset($path[0]) && $path[0] === '/';
    }

    /**
     * Whether the URI is a relative-path reference.
     *
     * A relative reference that does not begin with a slash character is termed a relative-path reference.
     *
     * @param UriInterface $uri
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public static function isRelativePathReference(UriInterface $uri)
    {
        $path = $uri->getPath();

        return $uri->getScheme() === '' && $uri->getAuthority() === '' && (!isset($path[0]) || $path[0] !== '/');
    }

    /**
     * Creates a new URI with a specific query string value removed.
     *
     * Any existing query string values that exactly match the provided key are
     * removed.
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string       $key Query string key to remove.
     *
     * @return UriInterface
     */
    public static function withoutQueryParam(UriInterface $uri, $key)
    {
        $current = $uri->getQuery();
        if ($current === '') {
            return $uri;
        }

        $decodedKey = rawurldecode($key);
        $result = array_filter(explode('&', $current), function ($part) use ($decodedKey) {
            return rawurldecode(explode('=', $part)[0]) !== $decodedKey;
        });

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Creates a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     *
     * A value of null will set the query string key without a value, e.g. "key"
     * instead of "key=value".
     *
     * @param UriInterface $uri   URI to use as a base.
     * @param string       $key   Key to set.
     * @param string|null  $value Value to set
     *
     * @return UriInterface
     */
    public static function withQueryParam(UriInterface $uri, $key, $value)
    {
        $current = $uri->getQuery();

        if ($current === '') {
            $result = [];
        } else {
            $decodedKey = rawurldecode($key);
            $result = array_filter(explode('&', $current), function ($part) use ($decodedKey) {
                return rawurldecode(explode('=', $part)[0]) !== $decodedKey;
            });
        }

        // Query string separators ("=", "&") within the key or value need to be encoded
        // (while preventing double-encoding) before setting the query string. All other
        // chars that need percent-encoding will be encoded by withQuery().
        $key = strtr($key, static::$replaceQuery);

        if ($value !== null) {
            $result[] = $key . '=' . strtr($value, static::$replaceQuery);
        } else {
            $result[] = $key;
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * @param UriInterface $uri
     * @param array $params
     * @return UriInterface
     */
    public static function withQueryParams(UriInterface $uri, array $params)
    {
        $query = http_build_query($params);

        return $uri->withQuery($query);
    }
}
