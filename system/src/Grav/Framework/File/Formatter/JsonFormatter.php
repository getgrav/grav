<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;

class JsonFormatter extends AbstractFormatter
{
    public function __construct(array $config = [])
    {
        $config += [
            'file_extension' => '.json',
            'encode_options' => 0,
            'decode_assoc' => true,
            'decode_depth' => 512,
            'decode_options' => 0
        ];

        parent::__construct($config);
    }

    /**
     * Returns options used in encode() function.
     *
     * @return int
     */
    public function getEncodeOptions(): int
    {
        return $this->getConfig('encode_options');
    }

    /**
     * Returns options used in decode() function.
     *
     * @return int
     */
    public function getDecodeOptions(): int
    {
        return $this->getConfig('decode_options');
    }

    /**
     * Returns recursion depth used in decode() function.
     *
     * @return int
     */
    public function getDecodeDepth(): int
    {
        return $this->getConfig('decode_depth');
    }

    /**
     * Returns true if JSON objects will be converted into associative arrays.
     *
     * @return bool
     */
    public function getDecodeAssoc(): bool
    {
        return $this->getConfig('decode_assoc');
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::encode()
     */
    public function encode($data): string
    {
        $encoded = @json_encode($data, $this->getEncodeOptions());

        if ($encoded === false && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Encoding JSON failed: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    public function decode($data)
    {
        $decoded = @json_decode($data, $this->getDecodeAssoc(), $this->getDecodeDepth(), $this->getDecodeOptions());

        if (null === $decoded && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Decoding JSON failed: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
