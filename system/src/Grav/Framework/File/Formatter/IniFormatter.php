<?php
/**
 * @package    Grav\Framework\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Formatter;

/**
 * Class IniFormatter
 * @package Grav\Framework\Formatter
 */
class IniFormatter implements FormatterInterface
{
    public function getFileExtension()
    {
        return 'ini';
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . '="' .  preg_replace(
                    ['/"/', '/\\\/', "/\t/", "/\n/", "/\r/"],
                    ['\"',  '\\\\', '\t',   '\n',   '\r'],
                    $value
                ) . "\"\n";
        }
        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data)
    {
        $decoded = @parse_ini_string($data);

        if ($decoded === false) {
            throw new \RuntimeException("Decoding INI format failed'");
        }

        return $decoded;
    }
}
