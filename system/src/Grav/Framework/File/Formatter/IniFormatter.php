<?php
/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

class IniFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    /**
     * IniFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config + [
                'file_extension' => '.ini'
            ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFileExtension()
    {
        return $this->config['file_extension'];
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
            throw new \RuntimeException('Decoding INI failed');
        }

        return $decoded;
    }
}
