<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\ObjectCollection;

/**
 * Class Flex
 * @package Grav\Framework\Flex
 */
class Flex implements \Countable
{
    /** @var array */
    protected $config;

    /** @var FlexDirectory[] */
    protected $types;

    /**
     * Flex constructor.
     * @param array $types  List of [type => blueprint file, ...]
     * @param array $config
     */
    public function __construct(array $types, array $config)
    {
        $this->config = $config;
        $this->types = [];

        foreach ($types as $type => $blueprint) {
            $this->addDirectoryType($type, $blueprint);
        }
    }

    /**
     * @param string $type
     * @param string $blueprint
     * @param array  $config
     * @return $this
     */
    public function addDirectoryType(string $type, string $blueprint, array $config = [])
    {
        $config = array_merge_recursive(['enabled' => true], $this->config['object'] ?? [], $config);

        $this->types[$type] = new FlexDirectory($type, $blueprint, $config);

        return $this;
    }

    /**
     * @param FlexDirectory $directory
     * @return $this
     */
    public function addDirectory(FlexDirectory $directory)
    {
        $this->types[$directory->getFlexType()] = $directory;

        return $this;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasDirectory(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @param array|string[] $types
     * @param bool $keepMissing
     * @return array|FlexDirectory[]
     */
    public function getDirectories(array $types = null, bool $keepMissing = false): array
    {
        if ($types === null) {
            return $this->types;
        }

        // Return the directories in the given order.
        $directories = [];
        foreach ($types as $type) {
            $directories[$type] = $this->types[$type] ?? null;
        }

        return $keepMissing ? $directories : array_filter($directories);
    }

    /**
     * @param string $type
     * @return FlexDirectory|null
     */
    public function getDirectory(string $type): ?FlexDirectory
    {
        return $this->types[$type] ?? null;
    }

    /**
     * @param string $type
     * @param array|null $keys
     * @param string|null $keyField
     * @return FlexCollectionInterface|null
     */
    public function getCollection(string $type, array $keys = null, string $keyField = null): ?FlexCollectionInterface
    {
        $directory = $type ? $this->getDirectory($type) : null;

        return $directory ? $directory->getCollection($keys, $keyField) : null;
    }

    /**
     * @param array $keys
     * @param array $options            In addition to the options in getObjects(), following options can be passed:
     *                                  collection_class:   Class to be used to create the collection. Defaults to ObjectCollection.
     * @return FlexCollectionInterface
     * @throws \RuntimeException
     */
    public function getMixedCollection(array $keys, array $options = []): FlexCollectionInterface
    {
        $collectionClass = $options['collection_class'] ?? ObjectCollection::class;
        if (!class_exists($collectionClass)) {
            throw new \RuntimeException(sprintf('Cannot create collection: Class %s does not exist', $collectionClass));
        }

        $objects = $this->getObjects($keys, $options);

        return new $collectionClass($objects);
    }

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
    public function getObjects(array $keys, array $options = []): array
    {
        $type = $options['type'] ?? null;
        $defaultType = $options['default_type'] ?? $type ?? null;
        $keyField = $options['key_field'] ?? 'flex_key';

        // Prepare empty result lists for all requested Flex types.
        $types = $options['types'] ?? (array)$type ?: null;
        if ($types) {
            $types = array_fill_keys($types, []);
        }
        $strict = isset($types);

        $guessed = [];
        if ($keyField === 'flex_key') {
            // We need to split Flex key lookups into individual directories.
            $undefined = [];
            $keyFieldFind = 'storage_key';

            foreach ($keys as $flexKey) {
                if (!$flexKey) {
                    continue;
                }

                $flexKey = (string)$flexKey;
                // Normalize key and type using fallback to default type if it was set.
                [$key, $type, $guess] = $this->resolveKeyAndType($flexKey, $defaultType);

                if ($type === '' && $types) {
                    // Add keys which are not associated to any Flex type. They will be included to every Flex type.
                    foreach ($types as $type => &$array) {
                        $array[] = $key;
                        $guessed[$key][] = "{$type}.obj:{$key}";
                    }
                    unset($array);
                } elseif (!$strict || isset($types[$type])) {
                    // Collect keys by their Flex type. If allowed types are defined, only include values from those types.
                    $types[$type][] = $key;
                    if ($guess) {
                        $guessed[$key][] = "{$type}.obj:{$key}";
                    }
                }
            }
        } else {
            // We are using a specific key field, make every key undefined.
            $undefined = $keys;
            $keyFieldFind = $keyField;
        }

        if (!$types) {
            return [];
        }

        $list = [[]];
        foreach ($types as $type => $typeKeys) {
            // Also remember to look up keys from undefined Flex types.
            $lookupKeys = $undefined ? array_merge($typeKeys, $undefined) : $typeKeys;

            $collection = $this->getCollection($type, $lookupKeys, $keyFieldFind);
            if ($collection && $keyFieldFind !== $keyField) {
                $collection = $collection->withKeyField($keyField);
            }

            $list[] = $collection ? $collection->toArray() : [];
        }

        // Merge objects from individual types back together.
        $list = array_merge(...$list);

        // Use the original key ordering.
        if (!$guessed) {
            $list = array_replace(array_fill_keys($keys, null), $list);
        } else {
            // We have mixed keys, we need to map flex keys back to storage keys.
            $results = [];
            foreach ($keys as $key) {
                $flexKey = $guessed[$key] ?? $key;
                if (\is_array($flexKey)) {
                    $result = null;
                    foreach ($flexKey as $tryKey) {
                        if ($result = $list[$tryKey] ?? null) {
                            // Use the first matching object (conflicting objects will be ignored for now).
                            break;
                        }
                    }
                } else {
                    $result = $list[$flexKey] ?? null;
                }

                $results[$key] = $result;
            }

            $list = $results;
        }

        // Remove missing objects if not asked to keep them.
        if (empty($option['keep_missing'])) {
            $list = array_filter($list);
        }

        return $list;
    }

    /**
     * @param string $key
     * @param string|null $type
     * @param string|null $keyField
     * @return FlexObjectInterface|null
     */
    public function getObject(string $key, string $type = null, string $keyField = null): ?FlexObjectInterface
    {
        if (null === $type && null === $keyField) {
            // Special handling for quick Flex key lookups.
            $keyField = 'storage_key';
            [$type, $key] = $this->resolveKeyAndType($key, $type);
        } else {
            $type = $this->resolveType($type);
        }

        if ($type === '' || $key === '') {
            return null;
        }

        $directory = $this->getDirectory($type);

        return $directory ? $directory->getObject($key, $keyField) : null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \count($this->types);
    }

    protected function resolveKeyAndType(string $flexKey, string $type = null): array
    {
        $guess = false;
        if (strpos($flexKey, ':') !== false) {
            [$type, $key] = explode(':',  $flexKey, 2);

            $type = $this->resolveType($type);
        } else {
            $key = $flexKey;
            $type = (string)$type;
            $guess = true;
        }

        return [$key, $type, $guess];
    }

    protected function resolveType(string $type = null): string
    {
        if (null !== $type && strpos($type, '.') !== false) {
            return preg_replace('|\.obj$|', '', $type);
        }

        return $type ?? '';
    }
}
