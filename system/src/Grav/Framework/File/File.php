<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

class File extends AbstractFile
{
    /**
     * Load a file from the filesystem.
     *
     * @return string|false
     */
    public function load()
    {
        return parent::load();
    }

    /**
     * Save file.
     *
     * @param  string $data
     * @throws \RuntimeException
     */
    public function save($data): void
    {
        if (!\is_string($data)) {
            throw new \RuntimeException('Cannot save data, string required');
        }

        parent::save($data);
    }
}
