<?php
/**
 * @package    Grav\Framework\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Formatter;

/**
 * Class JsonFormatter
 * @package Grav\Framework\Formatter
 */
class JsonFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'encode_options' => 0,
            'decode_assoc' => true
        ];
    }

    public function getFileExtension()
    {
        return 'json';
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        $encoded = json_encode($data, $this->config['encode_options']);
        if ($encoded === false) {
            throw new \RuntimeException('');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data)
    {
        return json_decode($data, $this->config['decode_assoc']);
    }
}
