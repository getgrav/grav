<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\JsonFile;
use RuntimeException;

/**
 * Class CompiledJsonFile
 * @package Grav\Common\File
 */
class CompiledJsonFile extends JsonFile
{
    use CompiledFile;

    /**
     * Encode contents into RAW string.
     *
     * The toolbox encoder casts a failed json_encode() (INF, NAN, a string which is not UTF-8, too deep
     * nesting) to an empty string, and saving that would replace everything the file holds with nothing.
     *
     * @param array $var
     * @param int $options
     * @return string
     * @throws RuntimeException
     */
    protected function encode($var, $options = 0)
    {
        // json_encode() accepts one level of nesting more than json_decode() reads back with the same depth,
        // so stay one below the default of the decoder: data which is written can always be read back.
        $encoded = json_encode($var, $options, 511);
        if ($encoded === false) {
            throw new RuntimeException('Encoding JSON failed: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Decode RAW string into contents.
     *
     * @param string $var
     * @param bool $assoc
     * @return array
     */
    protected function decode($var, $assoc = true)
    {
        return (array)json_decode($var, $assoc);
    }
}
