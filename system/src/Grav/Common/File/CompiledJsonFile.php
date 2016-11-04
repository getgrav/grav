<?php
/**
 * @package    Grav.Common.File
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\JsonFile;

class CompiledJsonFile extends JsonFile
{
    use CompiledFile;

    /**
     * Decode RAW string into contents.
     *
     * @param string $var
     * @param bool $assoc
     * @return array mixed
     */
    protected function decode($var, $assoc = true)
    {
        return (array) json_decode($var, $assoc);
    }
}
