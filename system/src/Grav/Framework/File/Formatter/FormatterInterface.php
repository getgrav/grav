<?php
/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

interface FormatterInterface
{
    /**
     * Get file extensions supported by current formatter (with dot).
     *
     * @return string[]
     */
    public function getSupportedFileExtensions();

    /**
     * Encode data into a string.
     *
     * @param array $data
     * @return string
     */
    public function encode($data);

    /**
     * Decode a string into data.
     *
     * @param string $data
     * @return array
     */
    public function decode($data);
}