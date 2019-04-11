<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Interfaces;

/**
 * Defines common interface for all file formatters.
 *
 * File formatters allow you to read and optionally write various file formats, such as:
 *
 * @used-by \Grav\Framework\File\Formatter\CsvFormatter         CVS
 * @used-by \Grav\Framework\File\Formatter\JsonFormatter        JSON
 * @used-by \Grav\Framework\File\Formatter\MarkdownFormatter    Markdown
 * @used-by \Grav\Framework\File\Formatter\SerializeFormatter   Serialized PHP
 * @used-by \Grav\Framework\File\Formatter\YamlFormatter        YAML
 *
 * @since 1.6
 */
interface FileFormatterInterface extends \Serializable
{
    /**
     * Get default file extension from current formatter (with dot).
     *
     * Default file extension is the first defined extension.
     *
     * @return string Returns file extension (can be empty).
     * @api
     */
    public function getDefaultFileExtension(): string;

    /**
     * Get file extensions supported by current formatter (with dot).
     *
     * @return string[] Returns list of all supported file extensions.
     * @api
     */
    public function getSupportedFileExtensions(): array;

    /**
     * Encode data into a string.
     *
     * @param mixed $data Data to be encoded.
     *
     * @return string Returns encoded data as a string.
     * @api
     */
    public function encode($data): string;

    /**
     * Decode a string into data.
     *
     * @param string $data String to be decoded.
     *
     * @return mixed Returns decoded data.
     * @api
     */
    public function decode($data);
}
