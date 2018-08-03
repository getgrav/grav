<?php
/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

class JsonFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'file_extension' => '.json',
            'encode_options' => 0,
            'decode_assoc' => true
        ];
    }

    /**
     * @deprecated 1.5 Use $formatter->getDefaultFileExtension() instead.
     */
    public function getFileExtension()
    {
        return $this->getDefaultFileExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultFileExtension()
    {
        $extensions = $this->getSupportedFileExtensions();

        return (string) reset($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFileExtensions()
    {
        return (array) $this->config['file_extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        $encoded = @json_encode($data, $this->config['encode_options']);

        if ($encoded === false) {
            throw new \RuntimeException('Encoding JSON failed');
        }

        return $encoded;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data)
    {
        $decoded = @json_decode($data, $this->config['decode_assoc']);

        if ($decoded === false) {
            throw new \RuntimeException('Decoding JSON failed');
        }

        return $decoded;
    }
}
