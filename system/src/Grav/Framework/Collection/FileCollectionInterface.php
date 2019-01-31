<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\Selectable;

/**
 * Collection of objects stored into a filesystem.
 *
 * @package Grav\Framework\Collection
 */
interface FileCollectionInterface extends CollectionInterface, Selectable
{
    public const INCLUDE_FILES = 1;
    public const INCLUDE_FOLDERS = 2;
    public const RECURSIVE = 4;

    /**
     * @return string
     */
    public function getPath();
}
