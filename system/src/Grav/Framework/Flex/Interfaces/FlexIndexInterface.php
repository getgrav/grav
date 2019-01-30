<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Flex\FlexDirectory;

/**
 * Interface FlexCollectionInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexIndexInterface extends FlexCollectionInterface
{
    /**
     * @param FlexDirectory $directory
     * @return static
     */
    public static function createFromStorage(FlexDirectory $directory) : FlexCollectionInterface;

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage) : array;
}
