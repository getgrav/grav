<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Framework\Collection\CollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Framework\Object\ObjectIndex;
use PSR\SimpleCache\InvalidArgumentException;

class FlexIndex extends ObjectIndex implements FlexCollectionInterface, FlexIndexInterface
{
    /** @var FlexDirectory */
    private $_flexDirectory;

    /** @var string */
    private $_keyField;

    /** @var array */
    private $_indexKeys;

    /**
     * @param FlexDirectory $directory
     * @return static
     */
    public static function createFromStorage(FlexDirectory $directory) : FlexCollectionInterface
    {
        return static::createFromArray(static::loadEntriesFromStorage($directory->getStorage()), $directory);
    }

    /**
     * @param array[] $entries
     * @param FlexDirectory $directory
     * @return static
     */
    public static function createFromArray(array $entries, FlexDirectory $directory) : FlexCollectionInterface
    {
        return new static($entries, $directory);
    }

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage) : array
    {
        return $storage->getExistingKeys();
    }

    /**
     * Initializes a new FlexIndex.
     *
     * @param array $entries
     * @param FlexDirectory $flexDirectory
     */
    public function __construct(array $entries, FlexDirectory $flexDirectory)
    {
        parent::__construct($entries);

        $this->_flexDirectory = $flexDirectory;
        $this->setKeyField(null);
    }

    /**
     * @return FlexDirectory
     */
    public function getFlexDirectory() : FlexDirectory
    {
        return $this->_flexDirectory;
    }

    /**
     * @param bool $prefix
     * @return string
     */
    public function getType($prefix = true)
    {
        $type = $prefix ? $this->getTypePrefix() : '';

        return $type . $this->_flexDirectory->getType();
    }

    /**
     * @return string[]
     */
    public function getStorageKeys()
    {
        return $this->getIndex('storage_key');
    }

    /**
     * @return string[]
     */
    public function getFlexKeys()
    {
        // Get storage keys for the objects.
        $keys = [];
        $type = $this->_flexDirectory->getType() . '.obj:';

        foreach ($this->getEntries() as $key => $value) {
            $keys[$key] = $value['flex_key'] ?? $type . $value['storage_key'];
        }

        return $keys;
    }

    /**
     * @return int[]
     */
    public function getTimestamps()
    {
        return $this->getIndex('storage_timestamp');
    }

    /**
     * @param string $indexKey
     * @return array
     */
    public function getIndex(string $indexKey)
    {
        // Get storage keys for the objects.
        $index = [];
        foreach ($this->getEntries() as $key => $value) {
            $index[$key] = $value[$indexKey] ?? null;
        }

        return $index;
    }

    /**
     * @return array
     */
    public function getMetaData(string $key) : array
    {
        return $this->getEntries()[$key] ?? [];
    }

    /**
     * @param string $keyField
     * @return FlexIndex
     */
    public function withKeyField(string $keyField = null) : self
    {
        $keyField = $keyField ?: 'key';
        if ($keyField === $this->getKeyField()) {
            return $this;
        }

        $entries = [];
        foreach ($this->getEntries() as $key => $value) {
            if (!isset($value['key'])) {
                $value['key'] = $key;
            }

            if (isset($value[$keyField])) {
                $entries[$value[$keyField]] = $value;
            }
        }

        return $this->createFrom($entries, $keyField);
    }

    /**
     * @return string
     */
    public function getKeyField() : string
    {
        return $this->_keyField ?? 'storage_key';
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        return $this->getType(true) . '.' . sha1(json_encode($this->getKeys()));
    }

    /**
     * @return string
     */
    public function getCacheChecksum()
    {
        return sha1($this->getCacheKey() . json_encode($this->getTimestamps()));
    }

    /**
     * @param array $orderings
     * @return FlexIndex|FlexCollection
     */
    public function orderBy(array $orderings)
    {
        if (!$orderings || !$this->count()) {
            return $this;
        }

        // Check if ordering needs to load the objects.
        if (array_diff_key($orderings, $this->getIndexKeys())) {
            return $this->__call('orderBy', [$orderings]);
        }

        // Ordering can be done by using index only.
        $previous = null;
        foreach (array_reverse($orderings) as $field => $ordering) {
            if ($this->getKeyField() === $field) {
                $keys = $this->getKeys();
                $search = array_combine($keys, $keys);
            } elseif ($field === 'flex_key') {
                $search = $this->getFlexKeys();
            } else {
                $search = $this->getIndex($field);
            }

            // Update current search to match the previous ordering.
            if (null !== $previous) {
                $search = array_replace($previous, $search);
            }

            // Order by current field.
            if ($ordering === 'DESC') {
                arsort($search, SORT_NATURAL);
            } else {
                asort($search, SORT_NATURAL);
            }

            $previous = $search;
        }

        return $this->createFrom(array_replace($previous, $this->getEntries()));
    }

    /**
     * {@inheritDoc}
     */
    public function call($method, array $arguments = [])
    {
        return $this->__call('call', [$method, $arguments]);
    }

    public function __call($name, $arguments)
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        /** @var FlexCollection $className */
        $className = $this->_flexDirectory->getCollectionClass();
        $cachedMethods = $className::getCachedMethods();

        if (!empty($cachedMethods[$name])) {
            $key = $this->getType(true) . '.' . sha1($name . '.' . json_encode($arguments) . $this->getCacheKey());

            $cache = $this->_flexDirectory->getCache('object');

            try {
                $result = $cache->get($key);

                // Make sure the keys aren't changed if the returned type is the same index type.
                if ($result instanceof self && $this->getType() === $result->getType()) {
                    $result = $result->withKeyField($this->getKeyField());
                }
            } catch (InvalidArgumentException $e) {
                /** @var Debugger $debugger */
                $debugger = Grav::instance()['debugger'];
                $debugger->addException($e);
            }

            if (null === $result) {
                $collection = $this->loadCollection();
                $result = $collection->{$name}(...$arguments);

                try {
                    // If flex collection is returned, convert it back to flex index.
                    if ($result instanceof FlexCollection) {
                        $cached = $result->getFlexDirectory()->getIndex($result->getKeys());
                    } else {
                        $cached = $result;
                    }

                    if ($cached === null) {
                        throw new \RuntimeException('Flex: Internal error');
                    }

                    $cache->set($key, $cached);
                } catch (InvalidArgumentException $e) {
                    $debugger->addException($e);

                    // TODO: log error.
                }
            }
        } else {
            $collection = $this->loadCollection();
            $result = $collection->{$name}(...$arguments);
            if (!isset($cachedMethods[$name])) {
                $class = \get_class($collection);
                $debugger->addMessage("Call '{$class}:{$name}()' isn't cached", 'debug');
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    public function serialize()
    {
        return serialize(['type' => $this->getType(false), 'entries' => $this->getEntries()]);
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        $this->_flexDirectory = Grav::instance()['flex_objects']->getDirectory($data['type']);
        $this->setEntries($data['entries']);
    }

    /**
     * @param array $entries
     * @param string $keyField
     * @return static
     */
    protected function createFrom(array $entries, string $keyField = null)
    {
        $index = new static($entries, $this->_flexDirectory);
        $index->setKeyField($keyField ?? $this->_keyField);

        return $index;
    }

    /**
     * @param string|null $keyField
     */
    protected function setKeyField(string $keyField = null)
    {
        $this->_keyField = $keyField ?? 'storage_key';
    }

    protected function getIndexKeys()
    {
        if (null === $this->_indexKeys) {
            $entries = $this->getEntries();
            $first = reset($entries);
            if ($first) {
                $keys = array_keys($first);
                $keys = array_combine($keys, $keys);
            } else {
                $keys = [];
            }

            $this->setIndexKeys($keys);
        }

        return $this->_indexKeys;
    }

    /**
     * @param array $indexKeys
     */
    protected function setIndexKeys(array $indexKeys)
    {
        // Add defaults.
        $indexKeys += [
            'key' => 'key',
            'storage_key' => 'storage_key',
            'storage_timestamp' => 'storage_timestamp',
            'flex_key' => 'flex_key'
        ];


        $this->_indexKeys = $indexKeys;
    }

    /**
     * @return string
     */
    protected function getTypePrefix()
    {
        return 'i.';
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return ObjectInterface|null
     */
    protected function loadElement($key, $value) : ?ObjectInterface
    {
        $objects = $this->_flexDirectory->loadObjects([$key => $value]);

        return $objects ? reset($objects) : null;
    }

    /**
     * @param array|null $entries
     * @return ObjectInterface[]
     */
    protected function loadElements(array $entries = null) : array
    {
        return $this->_flexDirectory->loadObjects($entries ?? $this->withKeyField()->getEntries());
    }

    /**
     * @param array|null $entries
     * @return ObjectCollectionInterface
     */
    protected function loadCollection(array $entries = null) : CollectionInterface
    {
        return $this->_flexDirectory->loadCollection($entries ?? $this->withKeyField()->getEntries());
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isAllowedElement($value) : bool
    {
        return $value instanceof FlexObject;
    }

    /**
     * @param FlexObjectInterface $object
     * @return mixed
     */
    protected function getElementMeta($object)
    {
        return $object->getTimestamp();
    }

    public function __debugInfo()
    {
        return [
            'type:private' => $this->getType(),
            'key:private' => $this->getKey(),
            'entries_key:private' => $this->getKeyField(),
            'entries:private' => $this->getEntries()
        ];
    }
}
