<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\MarkdownFile;

/**
 * Class CompiledMarkdownFile
 * @package Grav\Common\File
 */
class CompiledMarkdownFile extends MarkdownFile
{
    use CompiledFile;
}
