<?php

/**
 * @package    Grav\Framework\Route
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Route;

use Grav\Common\Uri;
use function dirname;
use function strlen;

/**
 * Class RouteFactory
 * @package Grav\Framework\Route
 */
class RouteFactory
{
    /** @var string */
    private static $root = '';
    /** @var string */
    private static $language = '';
    /** @var string */
    private static $delimiter = ':';

    /**
     * @param array $parts
     * @return Route
     */
    public static function createFromParts(array $parts): Route
    {
        return new Route($parts);
    }

    /**
     * @param Uri $uri
     * @return Route
     */
    public static function createFromLegacyUri(Uri $uri): Route
    {
        $parts = $uri->toArray();
        $parts += [
            'grav' => []
        ];
        $path = $parts['path'] ?? '';
        $parts['grav'] += [
            'root' => self::$root,
            'language' => self::$language,
            'route' => trim($path, '/'),
            'params' => $parts['params'] ?? [],
        ];

        return static::createFromParts($parts);
    }

    /**
     * @param string $path
     * @return Route
     */
    public static function createFromString(string $path): Route
    {
        $path = ltrim($path, '/');
        if (self::$language && mb_strpos($path, self::$language) === 0) {
            $path = ltrim(mb_substr($path, mb_strlen(self::$language)), '/');
        }

        $parts = [
            'path' => $path,
            'query' => '',
            'query_params' => [],
            'grav' => [
                'root' => self::$root,
                'language' => self::$language,
                'route' => static::trimParams($path),
                'params' => static::getParams($path)
            ],
        ];

        return new Route($parts);
    }

    /**
     * @return string
     */
    public static function getRoot(): string
    {
        return self::$root;
    }

    /**
     * @param string $root
     */
    public static function setRoot($root): void
    {
        self::$root = rtrim($root, '/');
    }

    /**
     * @return string
     */
    public static function getLanguage(): string
    {
        return self::$language;
    }

    /**
     * @param string $language
     */
    public static function setLanguage(string $language): void
    {
        self::$language = trim($language, '/');
    }

    /**
     * @return string
     */
    public static function getParamValueDelimiter(): string
    {
        return self::$delimiter;
    }

    /**
     * @param string $delimiter
     */
    public static function setParamValueDelimiter(string $delimiter): void
    {
        self::$delimiter = $delimiter ?: ':';
    }

    /**
     * @param array $params
     * @return string
     */
    public static function buildParams(array $params): string
    {
        if (!$params) {
            return '';
        }

        $delimiter = self::$delimiter;

        $output = [];
        foreach ($params as $key => $value) {
            $output[] = "{$key}{$delimiter}{$value}";
        }

        return implode('/', $output);
    }

    /**
     * @param string $path
     * @param bool $decode
     * @return string
     */
    public static function stripParams(string $path, bool $decode = false): string
    {
        $pos = strpos($path, self::$delimiter);

        if ($pos === false) {
            return $path;
        }

        $path = dirname(substr($path, 0, $pos));
        if ($path === '.') {
            return '';
        }

        return $decode ? rawurldecode($path) : $path;
    }

    /**
     * @param string $path
     * @return array
     */
    public static function getParams(string $path): array
    {
        $params = ltrim(substr($path, strlen(static::stripParams($path))), '/');

        return $params !== '' ? static::parseParams($params) : [];
    }

    /**
     * @param string $str
     * @return string
     */
    public static function trimParams(string $str): string
    {
        if ($str === '') {
            return $str;
        }

        $delimiter = self::$delimiter;

        /** @var array $params */
        $params = explode('/', $str);
        $list = [];
        foreach ($params as $param) {
            if (mb_strpos($param, $delimiter) === false) {
                $list[] = $param;
            }
        }

        return implode('/', $list);
    }

    /**
     * @param string $str
     * @return array
     */
    public static function parseParams(string $str): array
    {
        if ($str === '') {
            return [];
        }

        $delimiter = self::$delimiter;

        /** @var array $params */
        $params = explode('/', $str);
        $list = [];
        foreach ($params as &$param) {
            /** @var array $parts */
            $parts = explode($delimiter, $param, 2);
            if (isset($parts[1])) {
                $var = rawurldecode($parts[0]);
                $val = rawurldecode($parts[1]);
                $list[$var] = $val;
            }
        }

        return $list;
    }
}
