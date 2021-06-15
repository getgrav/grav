<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Exception;
use Grav\Common\Data\Blueprint;
use Grav\Framework\Cache\CacheInterface;

/**
 * Interface FlexDirectoryInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexDirectoryInterface extends FlexAuthorizeInterface
{
    /**
     * @return bool
     */
    public function isListed(): bool;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @return string
     */
    public function getFlexType(): string;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $name = null, $default = null);

    /**
     * @param string|null $name
     * @param array $options
     * @return FlexFormInterface
     * @internal
     */
    public function getDirectoryForm(string $name = null, array $options = []);

    /**
     * @return Blueprint
     * @internal
     */
    public function getDirectoryBlueprint();

    /**
     * @param string $name
     * @param array $data
     * @return void
     * @throws Exception
     * @internal
     */
    public function saveDirectoryConfig(string $name, array $data);

    /**
     * @param string|null $name
     * @return string
     */
    public function getDirectoryConfigUri(string $name = null): string;

    /**
     * Returns a new uninitialized instance of blueprint.
     *
     * Always use $object->getBlueprint() or $object->getForm()->getBlueprint() instead.
     *
     * @param string $type
     * @param string $context
     * @return Blueprint
     */
    public function getBlueprint(string $type = '', string $context = '');

    /**
     * @param string $view
     * @return string
     */
    public function getBlueprintFile(string $view = ''): string;

    /**
     * Get collection. In the site this will be filtered by the default filters (published etc).
     *
     * Use $directory->getIndex() if you want unfiltered collection.
     *
     * @param array|null $keys  Array of keys.
     * @param string|null $keyField  Field to be used as the key.
     * @return FlexCollectionInterface
     */
    public function getCollection(array $keys = null, string $keyField = null): FlexCollectionInterface;

    /**
     * Get the full collection of all stored objects.
     *
     * Use $directory->getCollection() if you want a filtered collection.
     *
     * @param array|null $keys  Array of keys.
     * @param string|null $keyField  Field to be used as the key.
     * @return FlexIndexInterface
     */
    public function getIndex(array $keys = null, string $keyField = null): FlexIndexInterface;

    /**
     * Returns an object if it exists. If no arguments are passed (or both of them are null), method creates a new empty object.
     *
     * Note: It is not safe to use the object without checking if the user can access it.
     *
     * @param string|null $key
     * @param string|null $keyField  Field to be used as the key.
     * @return FlexObjectInterface|null
     */
    public function getObject($key = null, string $keyField = null): ?FlexObjectInterface;

    /**
     * @param string|null $namespace
     * @return CacheInterface
     */
    public function getCache(string $namespace = null);

    /**
     * @return $this
     */
    public function clearCache();

    /**
     * @param string|null $key
     * @return string|null
     */
    public function getStorageFolder(string $key = null): ?string;

    /**
     * @param string|null $key
     * @return string|null
     */
    public function getMediaFolder(string $key = null): ?string;

    /**
     * @return FlexStorageInterface
     */
    public function getStorage(): FlexStorageInterface;

    /**
     * @param array $data
     * @param string $key
     * @param bool $validate
     * @return FlexObjectInterface
     */
    public function createObject(array $data, string $key = '', bool $validate = false): FlexObjectInterface;

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return FlexCollectionInterface
     */
    public function createCollection(array $entries, string $keyField = null): FlexCollectionInterface;

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return FlexIndexInterface
     */
    public function createIndex(array $entries, string $keyField = null): FlexIndexInterface;

    /**
     * @return string
     */
    public function getObjectClass(): string;

    /**
     * @return string
     */
    public function getCollectionClass(): string;

    /**
     * @return string
     */
    public function getIndexClass(): string;

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return FlexCollectionInterface
     */
    public function loadCollection(array $entries, string $keyField = null): FlexCollectionInterface;

    /**
     * @param array $entries
     * @return FlexObjectInterface[]
     * @internal
     */
    public function loadObjects(array $entries): array;

    /**
     * @return void
     */
    public function reloadIndex(): void;

    /**
     * @param string $scope
     * @param string $action
     * @return string
     */
    public function getAuthorizeRule(string $scope, string $action): string;
}
