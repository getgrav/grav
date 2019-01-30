<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Framework\File\Formatter\YamlFormatter;

abstract class Yaml
{
    /** @var YamlFormatter */
    private static $yaml;

    public static function parse($data)
    {
        if (null === static::$yaml) {
            static::init();
        }

        return static::$yaml->decode($data);
    }

    public static function dump($data, $inline = null, $indent = null)
    {
        if (null === static::$yaml) {
            static::init();
        }

        return static::$yaml->encode($data, $inline, $indent);
    }

    private static function init()
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
