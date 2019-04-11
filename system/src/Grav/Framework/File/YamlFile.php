<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Grav\Framework\File\Formatter\YamlFormatter;

/**
 * Class YamlFile
 * @package Grav\Framework\File
 */
class YamlFile extends DataFile
{
    /**
     * File constructor.
     * @param string $filepath
     * @param YamlFormatter $formatter
     */
    public function __construct($filepath, YamlFormatter $formatter)
    {
        parent::__construct($filepath, $formatter);
    }
}
