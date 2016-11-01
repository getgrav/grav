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

    public function content($var = null, $assoc = false)
    {
        if ($var !== null) {
            $this->content = $this->check($var);

            // Update RAW, too.
            $this->raw = $this->encode($this->content);

        } elseif ($this->content === null) {
            // Decode RAW file.
            $this->content = $this->decode($this->raw(), $assoc);
        }

        return $this->content;
    }
}
