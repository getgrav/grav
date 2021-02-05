<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Grav\Framework\File\Formatter\IniFormatter;

/**
 * Class IniFile
 * @package RocketTheme\Toolbox\File
 */
class IniFile extends DataFile
{
    /**
     * File constructor.
     * @param string $filepath
     * @param IniFormatter $formatter
     */
    public function __construct($filepath, IniFormatter $formatter)
    {
        parent::__construct($filepath, $formatter);
    }
}
