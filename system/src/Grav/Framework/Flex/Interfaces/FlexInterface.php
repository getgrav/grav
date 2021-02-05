<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Countable;
use Grav\Framework\Flex\FlexDirectory;
use RuntimeException;

/**
 * Interface FlexInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexInterface extends Countable
{
    /**
     * @param string $type
     * @param string $blueprint
     * @param array  $config
     * @return $this
     */
    public function addDirectoryType(string $type, string $blueprint, array $config = []);

    /**
     * @param FlexDirectory $directory
     * @return $this
     */
    public function addDirectory(FlexDirectory $directory);

    /**
     * @param string $type
     * @return bool
     */
    public function hasDirectory(string $type): bool;

    /**
     * @param array|string[]|null $types
     * @param bool $keepMissing
     * @return array<FlexDirectory|null>
     */
    public function getDirectories(array $types = null, bool $keepMissing = false): array;

    /**
     * @param string $type
     * @return FlexDirectory|null
     */
    public function getDirectory(string $type): ?FlexDirectory;

    /**
     * @param string $type
     * @param array|null $keys
     * @param string|null $keyField
     * @return FlexCollectionInterface|null
     */
    public function getCollection(string $type, array $keys = null, string $keyField = null): ?FlexCollectionInterface;

    /**
     * @param array $keys
     * @param array $options            In addition to the options in getObjects(), following options can be passed:
     *                                  collection_class:   Class to be used to create the collection. Defaults to ObjectCollection.
     * @return FlexCollectionInterface
     * @throws RuntimeException
     */
    public function getMixedCollection(array $keys, array $options = []): FlexCollectionInterface;

    /**
     * @param array $keys
     * @param array $options    Following optional options can be passed:
     *                          types:          List of allowed types.
     *                          type:           Allowed type if types isn't defined, otherwise acts as default_type.
     *                          default_type:   Set default type for objects given without type (only used if key_field isn't set).
     *                          keep_missing:   Set to true if you want to return missing objects as null.
     *                          key_field:      Key field which is used to match the objects.
     * @return array
     */
    public function getObjects(array $keys, array $options = []): array;

    /**
     * @param string $key
     * @param string|null $type
     * @param string|null $keyField
     * @return FlexObjectInterface|null
     */
    public function getObject(string $key, string $type = null, string $keyField = null): ?FlexObjectInterface;

    /**
     * @return int
     */
    public function count(): int;
}
