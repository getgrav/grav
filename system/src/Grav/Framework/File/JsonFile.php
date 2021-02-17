<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Grav\Framework\File\Formatter\JsonFormatter;

/**
 * Class JsonFile
 * @package Grav\Framework\File
 */
class JsonFile extends DataFile
{
    /**
     * File constructor.
     * @param string $filepath
     * @param JsonFormatter $formatter
     */
    public function __construct($filepath, JsonFormatter $formatter)
    {
        parent::__construct($filepath, $formatter);
    }
}
