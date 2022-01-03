<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;
use RuntimeException;
use function is_int;
use function is_string;

/**
 * Class JsonFormatter
 * @package Grav\Framework\File\Formatter
 */
class JsonFormatter extends AbstractFormatter
{
    /** @var array */
    protected $encodeOptions = [
        'JSON_FORCE_OBJECT' => JSON_FORCE_OBJECT,
        'JSON_HEX_QUOT' => JSON_HEX_QUOT,
        'JSON_HEX_TAG' => JSON_HEX_TAG,
        'JSON_HEX_AMP' => JSON_HEX_AMP,
        'JSON_HEX_APOS' => JSON_HEX_APOS,
        'JSON_INVALID_UTF8_IGNORE' => JSON_INVALID_UTF8_IGNORE,
        'JSON_INVALID_UTF8_SUBSTITUTE' => JSON_INVALID_UTF8_SUBSTITUTE,
        'JSON_NUMERIC_CHECK' => JSON_NUMERIC_CHECK,
        'JSON_PARTIAL_OUTPUT_ON_ERROR' => JSON_PARTIAL_OUTPUT_ON_ERROR,
        'JSON_PRESERVE_ZERO_FRACTION' => JSON_PRESERVE_ZERO_FRACTION,
        'JSON_PRETTY_PRINT' => JSON_PRETTY_PRINT,
        'JSON_UNESCAPED_LINE_TERMINATORS' => JSON_UNESCAPED_LINE_TERMINATORS,
        'JSON_UNESCAPED_SLASHES' => JSON_UNESCAPED_SLASHES,
        'JSON_UNESCAPED_UNICODE' => JSON_UNESCAPED_UNICODE,
        //'JSON_THROW_ON_ERROR' => JSON_THROW_ON_ERROR // PHP 7.3
    ];

    /** @var array */
    protected $decodeOptions = [
        'JSON_BIGINT_AS_STRING' => JSON_BIGINT_AS_STRING,
        'JSON_INVALID_UTF8_IGNORE' => JSON_INVALID_UTF8_IGNORE,
        'JSON_INVALID_UTF8_SUBSTITUTE' => JSON_INVALID_UTF8_SUBSTITUTE,
        'JSON_OBJECT_AS_ARRAY' => JSON_OBJECT_AS_ARRAY,
        //'JSON_THROW_ON_ERROR' => JSON_THROW_ON_ERROR // PHP 7.3
    ];

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
        $options = $this->getConfig('encode_options');
        if (!is_int($options)) {
            if (is_string($options)) {
                $list = preg_split('/[\s,|]+/', $options);
                $options = 0;
                if ($list) {
                    foreach ($list as $option) {
                        if (isset($this->encodeOptions[$option])) {
                            $options += $this->encodeOptions[$option];
                        }
                    }
                }
            } else {
                $options = 0;
            }
        }

        return $options;
    }

    /**
     * Returns options used in decode() function.
     *
     * @return int
     */
    public function getDecodeOptions(): int
    {
        $options = $this->getConfig('decode_options');
        if (!is_int($options)) {
            if (is_string($options)) {
                $list = preg_split('/[\s,|]+/', $options);
                $options = 0;
                if ($list) {
                    foreach ($list as $option) {
                        if (isset($this->decodeOptions[$option])) {
                            $options += $this->decodeOptions[$option];
                        }
                    }
                }
            } else {
                $options = 0;
            }
        }

        return $options;
    }

    /**
     * Returns recursion depth used in decode() function.
     *
     * @return int
     * @phpstan-return positive-int
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
            throw new RuntimeException('Encoding JSON failed: ' . json_last_error_msg());
        }

        return $encoded ?: '';
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    public function decode($data)
    {
        $decoded = @json_decode($data, $this->getDecodeAssoc(), $this->getDecodeDepth(), $this->getDecodeOptions());

        if (null === $decoded && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Decoding JSON failed: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
