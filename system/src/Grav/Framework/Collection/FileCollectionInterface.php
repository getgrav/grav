<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\Selectable;

/**
 * Collection of objects stored into a filesystem.
 *
 * @package Grav\Framework\Collection
 * @template TKey of array-key
 * @template T
 * @extends CollectionInterface<TKey,T>
 * @extends Selectable<TKey,T>
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
