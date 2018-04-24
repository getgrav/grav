<?php
/**
 * @package    Grav\Framework\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Formatter;

interface FormatterInterface
{
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