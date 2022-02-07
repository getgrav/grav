<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\JsonFile;

/**
 * Class CompiledJsonFile
 * @package Grav\Common\File
 */
class CompiledJsonFile extends JsonFile
{
    use CompiledFile;

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
