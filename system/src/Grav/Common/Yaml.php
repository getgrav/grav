<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Framework\File\Formatter\YamlFormatter;

/**
 * Class Yaml
 * @package Grav\Common
 */
abstract class Yaml
{
    /** @var YamlFormatter|null */
    protected static $yaml;

    /**
     * @param string $data
     * @return array
     */
    public static function parse($data)
    {
        if (null === static::$yaml) {
            static::init();
        }

        return static::$yaml->decode($data);
    }

    /**
     * @param array $data
     * @param int|null $inline
     * @param int|null $indent
     * @return string
     */
    public static function dump($data, $inline = null, $indent = null)
    {
        if (null === static::$yaml) {
            static::init();
        }

        return static::$yaml->encode($data, $inline, $indent);
    }

    /**
     * @return void
     */
    protected static function init()
    {
        $config = [
            'inline' => 5,
            'indent' => 2,
            'native' => true,
            'compat' => true
        ];

        static::$yaml = new YamlFormatter($config);
    }
}
