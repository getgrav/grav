<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Grav\Framework\File\Formatter\MarkdownFormatter;

/**
 * Class MarkdownFile
 * @package Grav\Framework\File
 */
class MarkdownFile extends DataFile
{
    /**
     * File constructor.
     * @param string $filepath
     * @param MarkdownFormatter $formatter
     */
    public function __construct($filepath, MarkdownFormatter $formatter)
    {
        parent::__construct($filepath, $formatter);
    }

    /**
     * @return array
     */
    public function load(): array
    {
        /** @var array */
        return parent::load();
    }
}
