<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Exception;
use Grav\Common\Debugger;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Session;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Collection\CollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Framework\Object\ObjectIndex;
use Monolog\Logger;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use function count;
use function get_class;
use function in_array;

/**
 * Class FlexIndex
 * @package Grav\Framework\Flex
 * @template T of FlexObjectInterface
 * @template C of FlexCollectionInterface
 * @extends ObjectIndex<string,T,C>
 * @implements FlexIndexInterface<T>
 * @mixin C
 */
class FlexIndex extends ObjectIndex implements FlexIndexInterface
{
    const VERSION = 1;

    /** @var FlexDirectory|null */
    private $_flexDirectory;
    /** @var string */
    private $_keyField = 'storage_key';
    /** @var array */
    private $_indexKeys;

    /**
     * @param FlexDirectory $directory
     * @return static
     * @phpstan-return static<T,C>
     */
    public static function createFromStorage(FlexDirectory $directory)
    {
        return static::createFromArray(static::loadEntriesFromStorage($directory->getStorage()), $directory);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::createFromArray()
     */
    public static function createFromArray(array $entries, FlexDirectory $directory, string $keyField = null)
    {
        $instance = new static($entries, $directory);
        $instance->setKeyField($keyField);

        return $instance;
    }

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage): array
    {
        return $storage->getExistingKeys();
    }

    /**
     * You can define indexes for fast lookup.
     *
     * Primary key: $meta['key']
     * Secondary keys:  $meta['my_field']
     *
     * @param array $meta
     * @param array $data
     * @param FlexStorageInterface $storage
     * @return void
     */
    public static function updateObjectMeta(array &$meta, array $data, FlexStorageInterface $storage)
    {
        // For backwards compatibility, no need to call this method when you override this method.
        static::updateIndexData($meta, $data);
    }

    /**
     * Initializes a new FlexIndex.
     *
     * @param array $entries
     * @param FlexDirectory|null $directory
     */
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        // @phpstan-ignore-next-line
        if (get_class($this) === __CLASS__) {
            user_error('Using ' . __CLASS__ . ' directly is deprecated since Grav 1.7, use \Grav\Common\Flex\Types\Generic\GenericIndex or your own class instead', E_USER_DEPRECATED);
        }

        parent::__construct($entries);

        $this->_flexDirectory = $directory;
        $this->setKeyField(null);
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->_key ?: $this->getFlexType() . '@@' . spl_object_hash($this);
    }

    /**
     * {@inheritdoc}
     * @see FlexCommonInterface::hasFlexFeature()
     */
    public function hasFlexFeature(string $name): bool
    {
        return in_array($name, $this->getFlexFeatures(), true);
    }

    /**
     * {@inheritdoc}
     * @see FlexCommonInterface::hasFlexFeature()
     */
    public function getFlexFeatures(): array
    {
        /** @var array $implements */
        $implements = class_implements($this->getFlexDirectory()->getCollectionClass());

        $list = [];
        foreach ($implements as $interface) {
            if ($pos = strrpos($interface, '\\')) {
                $interface = substr($interface, $pos+1);
            }

            $list[] = Inflector::hyphenize(str_replace('Interface', '', $interface));
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::search()
     */
    public function search(string $search, $properties = null, array $options = null)
    {
        $directory = $this->getFlexDirectory();
        $properties = $directory->getSearchProperties($properties);
        $options = $directory->getSearchOptions($options);

        return $this->__call('search', [$search, $properties, $options]);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::sort()
     */
    public function sort(array $orderings)
    {
        return $this->orderBy($orderings);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::filterBy()
     */
    public function filterBy(array $filters)
    {
        return $this->__call('filterBy', [$filters]);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexType()
     */
    public function getFlexType(): string
    {
        return $this->getFlexDirectory()->getFlexType();
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getFlexDirectory(): FlexDirectory
    {
        if (null === $this->_flexDirectory) {
            throw new RuntimeException('Flex Directory not defined, object is not fully defined');
        }

        return $this->_flexDirectory;
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getTimestamp()
     */
    public function getTimestamp(): int
    {
        $timestamps = $this->getTimestamps();

        return $timestamps ? max($timestamps) : time();
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getCacheKey()
     */
    public function getCacheKey(): string
    {
        return $this->getTypePrefix() . $this->getFlexType() . '.' . sha1(json_encode($this->getKeys()) . $this->_keyField);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getCacheChecksum()
     */
    public function getCacheChecksum(): string
    {
        $list = [];
        foreach ($this->getEntries() as $key => $value) {
            $list[$key] = $value['checksum'] ?? $value['storage_timestamp'];
        }

        return sha1((string)json_encode($list));
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getTimestamps()
     */
    public function getTimestamps(): array
    {
        return $this->getIndexMap('storage_timestamp');
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getStorageKeys()
     */
    public function getStorageKeys(): array
    {
        return $this->getIndexMap('storage_key');
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexKeys()
     */
    public function getFlexKeys(): array
    {
        // Get storage keys for the objects.
        $keys = [];
        $type = $this->getFlexDirectory()->getFlexType() . '.obj:';

        foreach ($this->getEntries() as $key => $value) {
            $keys[$key] = $value['flex_key'] ?? $type . $value['storage_key'];
        }

        return $keys;
    }

    /**
     * {@inheritdoc}
     * @see FlexIndexInterface::withKeyField()
     */
    public function withKeyField(string $keyField = null)
    {
        $keyField = $keyField ?: 'key';
        if ($keyField === $this->getKeyField()) {
            return $this;
        }

        $type = $keyField === 'flex_key' ? $this->getFlexDirectory()->getFlexType() . '.obj:' : '';
        $entries = [];
        foreach ($this->getEntries() as $key => $value) {
            if (!isset($value['key'])) {
                $value['key'] = $key;
            }

            if (isset($value[$keyField])) {
                $entries[$value[$keyField]] = $value;
            } elseif ($keyField === 'flex_key') {
                $entries[$type . $value['storage_key']] = $value;
            }
        }

        return $this->createFrom($entries, $keyField);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getIndex()
     */
    public function getIndex()
    {
        return $this;
    }

    /**
     * @return FlexCollectionInterface
     * @phpstan-return C
     */
    public function getCollection()
    {
        return $this->loadCollection();
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::render()
     */
    public function render(string $layout = null, array $context = [])
    {
        return $this->__call('render', [$layout, $context]);
    }

    /**
     * {@inheritdoc}
     * @see FlexIndexInterface::getFlexKeys()
     */
    public function getIndexMap(string $indexKey = null)
    {
        if (null === $indexKey) {
            return $this->getEntries();
        }

        // Get storage keys for the objects.
        $index = [];
        foreach ($this->getEntries() as $key => $value) {
            $index[$key] = $value[$indexKey] ?? null;
        }

        return $index;
    }

    /**
     * @param string $key
     * @return array
     */
    public function getMetaData($key): array
    {
        return $this->getEntries()[$key] ?? [];
    }

    /**
     * @return string
     */
    public function getKeyField(): string
    {
        return $this->_keyField;
    }

    /**
     * @param string|null $namespace
     * @return CacheInterface
     */
    public function getCache(string $namespace = null)
    {
        return $this->getFlexDirectory()->getCache($namespace);
    }

    /**
     * @param array $orderings
     * @return static
     * @phpstan-return static<T,C>
     */
    public function orderBy(array $orderings)
    {
        if (!$orderings || !$this->count()) {
            return $this;
        }

        // Handle primary key alias.
        $keyField = $this->getFlexDirectory()->getStorage()->getKeyField();
        if ($keyField !== 'key' && $keyField !== 'storage_key' && isset($orderings[$keyField])) {
            $orderings['key'] = $orderings[$keyField];
            unset($orderings[$keyField]);
        }

        // Check if ordering needs to load the objects.
        if (array_diff_key($orderings, $this->getIndexKeys())) {
            return $this->__call('orderBy', [$orderings]);
        }

        // Ordering can be done by using index only.
        $previous = null;
        foreach (array_reverse($orderings) as $field => $ordering) {
            $field = (string)$field;
            if ($this->getKeyField() === $field) {
                $keys = $this->getKeys();
                $search = array_combine($keys, $keys) ?: [];
            } elseif ($field === 'flex_key') {
                $search = $this->getFlexKeys();
            } else {
                $search = $this->getIndexMap($field);
            }

            // Update current search to match the previous ordering.
            if (null !== $previous) {
                $search = array_replace($previous, $search);
            }

            // Order by current field.
            if (strtoupper($ordering) === 'DESC') {
                arsort($search, SORT_NATURAL | SORT_FLAG_CASE);
            } else {
                asort($search, SORT_NATURAL | SORT_FLAG_CASE);
            }

            $previous = $search;
        }

        return $this->createFrom(array_replace($previous ?? [], $this->getEntries()));
    }

    /**
     * {@inheritDoc}
     */
    public function call($method, array $arguments = [])
    {
        return $this->__call('call', [$method, $arguments]);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function __call($name, $arguments)
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        /** @phpstan-var class-string $className */
        $className = $this->getFlexDirectory()->getCollectionClass();
        $cachedMethods = $className::getCachedMethods();

        $flexType = $this->getFlexType();

        if (!empty($cachedMethods[$name])) {
            $type = $cachedMethods[$name];
            if ($type === 'session') {
                /** @var Session $session */
                $session = Grav::instance()['session'];
                $cacheKey = $session->getId() . ($session->user->username ?? '');
            } else {
                $cacheKey = '';
            }
            $key = "{$flexType}.idx." . sha1($name . '.' . $cacheKey . json_encode($arguments) . $this->getCacheKey());
            $checksum = $this->getCacheChecksum();

            $cache = $this->getCache('object');

            try {
                $cached = $cache->get($key);
                $test = $cached[0] ?? null;
                $result = $test === $checksum ? ($cached[1] ?? null) : null;

                // Make sure the keys aren't changed if the returned type is the same index type.
                if ($result instanceof self && $flexType === $result->getFlexType()) {
                    $result = $result->withKeyField($this->getKeyField());
                }
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);
            }

            if (!isset($result)) {
                $collection = $this->loadCollection();
                $result = $collection->{$name}(...$arguments);
                $debugger->addMessage("Cache miss: '{$flexType}::{$name}()'", 'debug');

                try {
                    // If flex collection is returned, convert it back to flex index.
                    if ($result instanceof FlexCollection) {
                        $cached = $result->getFlexDirectory()->getIndex($result->getKeys(), $this->getKeyField());
                    } else {
                        $cached = $result;
                    }

                    $cache->set($key, [$checksum, $cached]);
                } catch (InvalidArgumentException $e) {
                    $debugger->addException($e);

                    // TODO: log error.
                }
            }
        } else {
            $collection = $this->loadCollection();
            if (\is_callable([$collection, $name])) {
                $result = $collection->{$name}(...$arguments);
                if (!isset($cachedMethods[$name])) {
                    $debugger->addMessage("Call '{$flexType}:{$name}()' isn't cached", 'debug');
                }
            } else {
                $result = null;
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return ['type' => $this->getFlexType(), 'entries' => $this->getEntries()];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->_flexDirectory = Grav::instance()['flex']->getDirectory($data['type']);
        $this->setEntries($data['entries']);
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function __debugInfo()
    {
        return [
            'type:private' => $this->getFlexType(),
            'key:private' => $this->getKey(),
            'entries_key:private' => $this->getKeyField(),
            'entries:private' => $this->getEntries()
        ];
    }

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return static
     * @phpstan-return static<T,C>
     */
    protected function createFrom(array $entries, string $keyField = null)
    {
        /** @phpstan-var static<T,C> $index */
        $index = new static($entries, $this->getFlexDirectory());
        $index->setKeyField($keyField ?? $this->_keyField);

        return $index;
    }

    /**
     * @param string|null $keyField
     * @return void
     */
    protected function setKeyField(string $keyField = null)
    {
        $this->_keyField = $keyField ?? 'storage_key';
    }

    /**
     * @return array
     */
    protected function getIndexKeys()
    {
        if (null === $this->_indexKeys) {
            $entries = $this->getEntries();
            $first = reset($entries);
            if ($first) {
                $keys = array_keys($first);
                $keys = array_combine($keys, $keys) ?: [];
            } else {
                $keys = [];
            }

            $this->setIndexKeys($keys);
        }

        return $this->_indexKeys;
    }

    /**
     * @param array $indexKeys
     * @return void
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
     * @phpstan-return T|null
     */
    protected function loadElement($key, $value): ?ObjectInterface
    {
        /** @phpstan-var T[] $objects */
        $objects = $this->getFlexDirectory()->loadObjects([$key => $value]);

        return $objects ? reset($objects): null;
    }

    /**
     * @param array|null $entries
     * @return ObjectInterface[]
     * @phpstan-return T[]
     */
    protected function loadElements(array $entries = null): array
    {
        /** @phpstan-var T[] $objects */
        $objects = $this->getFlexDirectory()->loadObjects($entries ?? $this->getEntries());

        return $objects;
    }

    /**
     * @param array|null $entries
     * @return CollectionInterface
     * @phpstan-return C
     */
    protected function loadCollection(array $entries = null): CollectionInterface
    {
        /** @var C $collection */
        $collection = $this->getFlexDirectory()->loadCollection($entries ?? $this->getEntries(), $this->_keyField);

        return $collection;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isAllowedElement($value): bool
    {
        return $value instanceof FlexObject;
    }

    /**
     * @param FlexObjectInterface $object
     * @return mixed
     */
    protected function getElementMeta($object)
    {
        return $object->getMetaData();
    }

    /**
     * @param FlexObjectInterface $element
     * @return string
     */
    protected function getCurrentKey($element)
    {
        $keyField = $this->getKeyField();
        if ($keyField === 'storage_key') {
            return $element->getStorageKey();
        }
        if ($keyField === 'flex_key') {
            return $element->getFlexKey();
        }
        if ($keyField === 'key') {
            return $element->getKey();
        }

        return $element->getKey();
    }

    /**
     * @param FlexStorageInterface $storage
     * @param array $index      Saved index
     * @param array $entries    Updated index
     * @param array $options
     * @return array            Compiled list of entries
     */
    protected static function updateIndexFile(FlexStorageInterface $storage, array $index, array $entries, array $options = []): array
    {
        $indexFile = static::getIndexFile($storage);
        if (null === $indexFile) {
            return $entries;
        }

        // Calculate removed objects.
        $removed = array_diff_key($index, $entries);

        // First get rid of all removed objects.
        if ($removed) {
            $index = array_diff_key($index, $removed);
        }

        if ($entries && empty($options['force_update'])) {
            // Calculate difference between saved index and current data.
            foreach ($index as $key => $entry) {
                $storage_key = $entry['storage_key'] ?? null;
                if (isset($entries[$storage_key]) && $entries[$storage_key]['storage_timestamp'] === $entry['storage_timestamp']) {
                    // Entry is up to date, no update needed.
                    unset($entries[$storage_key]);
                }
            }

            if (empty($entries) && empty($removed)) {
                // No objects were added, updated or removed.
                return $index;
            }
        } elseif (!$removed) {
            // There are no objects and nothing was removed.
            return [];
        }

        // Index should be updated, lock the index file for saving.
        $indexFile->lock();

        // Read all the data rows into an array using chunks of 100.
        $keys = array_fill_keys(array_keys($entries), null);
        $chunks = array_chunk($keys, 100, true);
        $updated = $added = [];
        foreach ($chunks as $keys) {
            $rows = $storage->readRows($keys);

            $keyField = $storage->getKeyField();

            // Go through all the updated objects and refresh their index data.
            foreach ($rows as $key => $row) {
                if (null !== $row || !empty($options['include_missing'])) {
                    $entry = $entries[$key] + ['key' => $key];
                    if ($keyField !== 'storage_key' && isset($row[$keyField])) {
                        $entry['key'] = $row[$keyField];
                    }
                    static::updateObjectMeta($entry, $row ?? [], $storage);
                    if (isset($row['__ERROR'])) {
                        $entry['__ERROR'] = true;
                        static::onException(new RuntimeException(sprintf('Object failed to load: %s (%s)', $key,
                            $row['__ERROR'])));
                    }
                    if (isset($index[$key])) {
                        // Update object in the index.
                        $updated[$key] = $entry;
                    } else {
                        // Add object into the index.
                        $added[$key] = $entry;
                    }

                    // Either way, update the entry.
                    $index[$key] = $entry;
                } elseif (isset($index[$key])) {
                    // Remove object from the index.
                    $removed[$key] = $index[$key];
                    unset($index[$key]);
                }
            }
            unset($rows);
        }

        // Sort the index before saving it.
        ksort($index, SORT_NATURAL | SORT_FLAG_CASE);

        static::onChanges($index, $added, $updated, $removed);

        $indexFile->save(['version' => static::VERSION, 'timestamp' => time(), 'count' => count($index), 'index' => $index]);
        $indexFile->unlock();

        return $index;
    }

    /**
     * @param array $entry
     * @param array $data
     * @return void
     * @deprecated 1.7 Use static ::updateObjectMeta() method instead.
     */
    protected static function updateIndexData(array &$entry, array $data)
    {
    }

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    protected static function loadIndex(FlexStorageInterface $storage)
    {
        $indexFile = static::getIndexFile($storage);

        if ($indexFile) {
            $data = [];
            try {
                $data = (array)$indexFile->content();
                $version = $data['version'] ?? null;
                if ($version !== static::VERSION) {
                    $data = [];
                }
            } catch (Exception $e) {
                $e = new RuntimeException(sprintf('Index failed to load: %s', $e->getMessage()), $e->getCode(), $e);

                static::onException($e);
            }

            if ($data) {
                return $data;
            }
        }

        return ['version' => static::VERSION, 'timestamp' => 0, 'count' => 0, 'index' => []];
    }

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    protected static function loadEntriesFromIndex(FlexStorageInterface $storage)
    {
        $data = static::loadIndex($storage);

        return $data['index'] ?? [];
    }

    /**
     * @param FlexStorageInterface $storage
     * @return CompiledYamlFile|CompiledJsonFile|null
     */
    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        if (!method_exists($storage, 'isIndexed') || !$storage->isIndexed()) {
            return null;
        }

        $path = $storage->getStoragePath();
        if (!$path) {
            return null;
        }

        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = $locator->findResource("{$path}/index.yaml", true, true);

        return CompiledYamlFile::instance($filename);
    }

    /**
     * @param Exception $e
     * @return void
     */
    protected static function onException(Exception $e)
    {
        $grav = Grav::instance();

        /** @var Logger $logger */
        $logger = $grav['log'];
        $logger->addAlert($e->getMessage());

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->addException($e);
        $debugger->addMessage($e, 'error');
    }

    /**
     * @param array $entries
     * @param array $added
     * @param array $updated
     * @param array $removed
     * @return void
     */
    protected static function onChanges(array $entries, array $added, array $updated, array $removed)
    {
        $addedCount = count($added);
        $updatedCount = count($updated);
        $removedCount = count($removed);

        if ($addedCount + $updatedCount + $removedCount) {
            $message = sprintf('Index updated, %d objects (%d added, %d updated, %d removed).', count($entries), $addedCount, $updatedCount, $removedCount);

            $grav = Grav::instance();

            /** @var Debugger $debugger */
            $debugger = $grav['debugger'];
            $debugger->addMessage($message, 'debug');
        }
    }

    // DEPRECATED METHODS

    /**
     * @param bool $prefix
     * @return string
     * @deprecated 1.6 Use `->getFlexType()` instead.
     */
    public function getType($prefix = false)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use ->getFlexType() method instead', E_USER_DEPRECATED);

        $type = $prefix ? $this->getTypePrefix() : '';

        return $type . $this->getFlexType();
    }
}
