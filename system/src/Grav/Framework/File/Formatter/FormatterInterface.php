<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

interface FormatterInterface extends \Serializable
{
    /**
     * Get default file extension from current formatter (with dot).
     *
     * Default file extension is the first defined extension.
     *
     * @return string File extension (can be empty).
     */
    public function getDefaultFileExtension(): string;

    /**
     * Get file extensions supported by current formatter (with dot).
     *
     * @return string[]
     */
    public function getSupportedFileExtensions(): array;

    /**
     * Encode data into a string.
     *
     * @param array $data
     * @return string
     */
    public function encode($data): string;

    /**
     * Decode a string into data.
     *
     * @param string $data
     * @return mixed
     */
    public function decode($data);
}
