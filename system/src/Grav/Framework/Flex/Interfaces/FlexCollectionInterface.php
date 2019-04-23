<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Flex\Flex;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Defines a collection of Flex Objects.
 *
 * @used-by \Grav\Framework\Flex\FlexCollection
 * @since 1.6
 */
interface FlexCollectionInterface extends FlexCommonInterface, ObjectCollectionInterface, NestedObjectInterface
{
    /**
     * Creates a Flex Collection from an array.
     *
     * @used-by FlexDirectory::createCollection()   Official method to create a Flex Collection.
     *
     * @param FlexObjectInterface[] $entries    Associated array of Flex Objects to be included in the collection.
     * @param FlexDirectory         $directory  Flex Directory where all the objects belong into.
     * @param string                $keyField   Key field used to index the collection.
     *
     * @return static                           Returns a new Flex Collection.
     */
    public static function createFromArray(array $entries, FlexDirectory $directory, string $keyField = null);

    /**
     * Creates a new Flex Collection.
     *
     * @used-by FlexDirectory::createCollection()   Official method to create Flex Collection.
     *
     * @param FlexObjectInterface[] $entries    Associated array of Flex Objects to be included in the collection.
     * @param FlexDirectory         $directory  Flex Directory where all the objects belong into.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $entries = [], FlexDirectory $directory = null);

    /**
     * Search a string from the collection.
     *
     * @param string                $search     Search string.
     * @param string|string[]|null  $properties Properties to search for, defaults to configured properties.
     * @param array|null            $options    Search options, defaults to configured options.
     *
     * @return FlexCollectionInterface          Returns a Flex Collection with only matching objects.
     * @api
     */
    public function search(string $search, $properties = null, array $options = null);

    /**
     * Sort the collection.
     *
     * @param array $orderings Pair of [property => 'ASC'|'DESC', ...].
     *
     * @return FlexCollectionInterface Returns a sorted version from the collection.
     */
    public function sort(array $orderings);

    /**
     * Filter collection by filter array with keys and values.
     *
     * @param array $filters
     * @return FlexCollectionInterface
     */
    public function filterBy(array $filters);

    /**
     * Get timestamps from all the objects in the collection.
     *
     * This method can be used for example in caching.
     *
     * @return int[] Returns [key => timestamp, ...] pairs.
     */
    public function getTimestamps(): array;

    /**
     * Get storage keys from all the objects in the collection.
     *
     * @see FlexDirectory::getObject()  If you want to get Flex Object from the Flex Directory.
     *
     * @return string[] Returns [key => storage_key, ...] pairs.
     */
    public function getStorageKeys(): array;

    /**
     * Get Flex keys from all the objects in the collection.
     *
     * @see Flex::getObjects()  If you want to get list of Flex Objects from any Flex Directory.
     *
     * @return string[] Returns[key => flex_key, ...] pairs.
     */
    public function getFlexKeys(): array;

    /**
     * Return new collection with a different key.
     *
     * @param string|null $keyField Switch key field of the collection.
     *
     * @return FlexCollectionInterface  Returns a new Flex Collection with new key field.
     * @api
     */
    public function withKeyField(string $keyField = null);

    /**
     * Get Flex Index from the Flex Collection.
     *
     * @return FlexIndexInterface   Returns a Flex Index from the current collection.
     */
    public function getIndex();
}
