<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

/**
 * Interface FlexStorageInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexStorageInterface
{
    /**
     * StorageInterface constructor.
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return  array
     */
    public function getExistingKeys() : array;

    /**
     * Check if storage has a row for the key.
     *
     * @param string $key
     * @return bool
     */
    public function hasKey(string $key) : bool;

    /**
     * Create new rows. New keys will be assigned when the objects are created.
     *
     * @param  array  $rows  Array of rows.
     * @return array  Returns created rows. Note that existing rows will fail to save and have null value.
     */
    public function createRows(array $rows) : array;

    /**
     * Read rows. If you pass object or array as value, that value will be used to save I/O.
     *
     * @param  array  $rows  Array of [key => row] pairs.
     * @param  array  $fetched  Optional variable for storing only fetched items.
     * @return array  Returns rows. Note that non-existing rows have null value.
     */
    public function readRows(array $rows, array &$fetched = null) : array;

    /**
     * Update existing rows.
     *
     * @param  array  $rows  Array of [key => row] pairs.
     * @return array  Returns updated rows. Note that non-existing rows will fail to save and have null value.
     */
    public function updateRows(array $rows) : array;

    /**
     * Delete rows.
     *
     * @param  array  $rows  Array of [key => row] pairs.
     * @return array  Returns deleted rows. Note that non-existing rows have null value.
     */
    public function deleteRows(array $rows) : array;

    /**
     * Replace rows regardless if they exist or not.
     *
     * All rows should have a specified key for this to work.
     *
     * @param  array $rows  Array of [key => row] pairs.
     * @return array  Returns both created and updated rows.
     */
    public function replaceRows(array $rows) : array;

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    public function renameRow(string $src, string $dst) : bool;

    /**
     * Get filesystem path for the collection or object storage.
     *
     * @param  string|null $key
     * @return string
     */
    public function getStoragePath(string $key = null) : string;

    /**
     * Get filesystem path for the collection or object media.
     *
     * @param  string|null $key
     * @return string
     */
    public function getMediaPath(string $key = null) : string;
}
