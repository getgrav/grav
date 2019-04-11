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
 * Defines Indexes for Flex Objects.
 *
 * Flex indexes are similar to database indexes, they contain indexed fields which can be used to quickly look up or
 * find the objects without loading them.
 *
 * @used-by \Grav\Framework\Flex\FlexIndex
 * @since 1.6
 */
interface FlexIndexInterface extends FlexCollectionInterface
{
    /**
     * Helper method to create Flex Index.
     *
     * @used-by FlexDirectory::getIndex()   Official method to get Index from a Flex Directory.
     *
     * @param FlexDirectory $directory Flex directory.
     *
     * @return static Returns a new Flex Index.
     */
    public static function createFromStorage(FlexDirectory $directory);

    /**
     * Method to load index from the object storage, usually filesystem.
     *
     * @used-by FlexDirectory::getIndex()   Official method to get Index from a Flex Directory.
     *
     * @param FlexStorageInterface $storage Flex Storage associated to the directory.
     *
     * @return array Returns a list of existing objects [storage_key => [storage_key => xxx, storage_timestamp => 123456, ...]]
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage): array;

    /**
     * Return new collection with a different key.
     *
     * @param string|null $keyField Switch key field of the collection.
     *
     * @return FlexIndexInterface  Returns a new Flex Collection with new key field.
     * @api
     */
    public function withKeyField(string $keyField = null);

    /**
     * @param string $indexKey
     * @return array
     */
    public function getIndexMap(string $indexKey = null);
}
