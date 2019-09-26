<?php

/**
 * @package    Grav\Framework\Route
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Route;

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

    public static function createFromParts($parts)
    {
        return new Route($parts);
    }

    public static function createFromString($path)
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

    public static function getRoot()
    {
        return self::$root;
    }

    public static function setRoot($root)
    {
        self::$root = rtrim($root, '/');
    }

    public static function getLanguage()
    {
        return self::$language;
    }

    public static function setLanguage($language)
    {
        self::$language = trim($language, '/');
    }

    public static function getParamValueDelimiter()
    {
        return self::$delimiter;
    }

    public static function setParamValueDelimiter($delimiter)
    {
        self::$delimiter = $delimiter ?: ':';
    }

    /**
     * @param array $params
     * @return string
     */
    public static function buildParams(array $params)
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
    public static function stripParams($path, $decode = false)
    {
        $pos = strpos($path, self::$delimiter);

        if ($pos === false) {
            return $path;
        }

        $path = \dirname(substr($path, 0, $pos));
        if ($path === '.') {
            return '';
        }

        return $decode ? rawurldecode($path) : $path;
    }

    /**
     * @param string $path
     * @return array
     */
    public static function getParams($path)
    {
        $params = ltrim(substr($path, \strlen(static::stripParams($path))), '/');

        return $params !== '' ? static::parseParams($params) : [];
    }

    public static function trimParams($str)
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
    public static function parseParams($str)
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
