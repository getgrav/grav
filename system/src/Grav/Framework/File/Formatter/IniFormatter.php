<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;

/**
 * Class IniFormatter
 * @package Grav\Framework\File\Formatter
 */
class IniFormatter extends AbstractFormatter
{
    /**
     * IniFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $config += [
            'file_extension' => '.ini'
        ];

        parent::__construct($config);
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::encode()
     */
    public function encode($data): string
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
     * @see FileFormatterInterface::decode()
     */
    public function decode($data): array
    {
        $decoded = @parse_ini_string($data);

        if ($decoded === false) {
            throw new \RuntimeException('Decoding INI failed');
        }

        return $decoded;
    }
}
