<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

/**
 * Defines Flex Storage layer.
 *
 * @since 1.6
 */
interface FlexStorageInterface
{
    /**
     * StorageInterface constructor.
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * @return string
     */
    public function getKeyField(): string;

    /**
     * @param string[] $keys
     * @param bool $reload
     * @return array
     */
    public function getMetaData(array $keys, bool $reload = false): array;

    /**
     * Returns associated array of all existing storage keys with a timestamp.
     *
     * @return  array Returns all existing keys as `[key => [storage_key => key, storage_timestamp => timestamp], ...]`.
     */
    public function getExistingKeys(): array;

    /**
     * Check if the key exists in the storage.
     *
     * @param string $key Storage key of an object.
     * @return bool Returns `true` if the key exists in the storage, `false` otherwise.
     */
    public function hasKey(string $key): bool;

    /**
     * Check if the key exists in the storage.
     *
     * @param string[] $keys Storage key of an object.
     * @return bool[] Returns keys with `true` if the key exists in the storage, `false` otherwise.
     */
    public function hasKeys(array $keys): array;

    /**
     * Create new rows into the storage.
     *
     * New keys will be assigned when the objects are created.
     *
     * @param  array  $rows  List of rows as `[row, ...]`.
     * @return array  Returns created rows as `[key => row, ...] pairs.
     */
    public function createRows(array $rows): array;

    /**
     * Read rows from the storage.
     *
     * If you pass object or array as value, that value will be used to save I/O.
     *
     * @param  array  $rows  Array of `[key => row, ...]` pairs.
     * @param  array|null $fetched  Optional reference to store only fetched items.
     * @return array  Returns rows. Note that non-existing rows will have `null` as their value.
     */
    public function readRows(array $rows, array &$fetched = null): array;

    /**
     * Update existing rows in the storage.
     *
     * @param  array  $rows  Array of `[key => row, ...]` pairs.
     * @return array  Returns updated rows. Note that non-existing rows will not be saved and have `null` as their value.
     */
    public function updateRows(array $rows): array;

    /**
     * Delete rows from the storage.
     *
     * @param  array  $rows  Array of `[key => row, ...]` pairs.
     * @return array  Returns deleted rows. Note that non-existing rows have `null` as their value.
     */
    public function deleteRows(array $rows): array;

    /**
     * Replace rows regardless if they exist or not.
     *
     * All rows should have a specified key for replace to work properly.
     *
     * @param  array $rows  Array of `[key => row, ...]` pairs.
     * @return array  Returns both created and updated rows.
     */
    public function replaceRows(array $rows): array;

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    public function copyRow(string $src, string $dst): bool;

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    public function renameRow(string $src, string $dst): bool;

    /**
     * Get filesystem path for the collection or object storage.
     *
     * @param  string|null $key Optional storage key.
     * @return string|null Path in the filesystem. Can be URI or null if storage is not filesystem based.
     */
    public function getStoragePath(string $key = null): ?string;

    /**
     * Get filesystem path for the collection or object media.
     *
     * @param  string|null $key Optional storage key.
     * @return string|null Path in the filesystem. Can be URI or null if media isn't supported.
     */
    public function getMediaPath(string $key = null): ?string;
}
