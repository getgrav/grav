<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Debugger;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Session;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Collection\CollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Framework\Object\ObjectIndex;
use Monolog\Logger;
use Psr\SimpleCache\InvalidArgumentException;

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
     * Initializes a new FlexIndex.
     *
     * @param array $entries
     * @param FlexDirectory|null $directory
     */
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        parent::__construct($entries);

        $this->_flexDirectory = $directory;
        $this->setKeyField(null);
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::search()
     */
    public function search(string $search, $properties = null, array $options = null)
    {
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
        return $this->_flexDirectory->getFlexType();
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getFlexDirectory(): FlexDirectory
    {
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
        return sha1($this->getCacheKey() . json_encode($this->getTimestamps()));
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
        $type = $this->_flexDirectory->getFlexType() . '.obj:';

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

        $type = $keyField === 'flex_key' ? $this->_flexDirectory->getFlexType() . '.obj:' : '';
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
     * {@inheritdoc}
     * @see FlexCollectionInterface::render()
     */
    public function render(string $layout = null, array $context = [])
    {
        return $this->__call('render', [$layout, $context]);
    }

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
     * @return array
     */
    public function getMetaData(string $key): array
    {
        return $this->getEntries()[$key] ?? [];
    }

    /**
     * @return string
     */
    public function getKeyField() : string
    {
        return $this->_keyField ?? 'storage_key';
    }

    /**
     * @param string|null $namespace
     * @return CacheInterface
     */
    public function getCache(string $namespace = null)
    {
        return $this->_flexDirectory->getCache($namespace);
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

        $flexType = $this->getFlexType();

        if (!empty($cachedMethods[$name])) {
            $type = $cachedMethods[$name];
            if ($type === 'session') {
                /** @var Session $session */
                $session = Grav::instance()['session'];
                $cacheKey = $session->getId() . $session->user->username;
            } else {
                $cacheKey = '';
            }
            $key = "{$flexType}.idx." . sha1($name . '.' . $cacheKey . json_encode($arguments) . $this->getCacheKey());

            $cache = $this->getCache('object');

            try {
                $result = $cache->get($key);

                // Make sure the keys aren't changed if the returned type is the same index type.
                if ($result instanceof self && $flexType === $result->getFlexType()) {
                    $result = $result->withKeyField($this->getKeyField());
                }
            } catch (InvalidArgumentException $e) {
                /** @var Debugger $debugger */
                $debugger = Grav::instance()['debugger'];
                $debugger->addException($e);
            }

            if (!isset($result)) {
                $collection = $this->loadCollection();
                $result = $collection->{$name}(...$arguments);

                try {
                    // If flex collection is returned, convert it back to flex index.
                    if ($result instanceof FlexCollection) {
                        $cached = $result->getFlexDirectory()->getIndex($result->getKeys(), $this->getKeyField());
                    } else {
                        $cached = $result;
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
        return serialize(['type' => $this->getFlexType(), 'entries' => $this->getEntries()]);
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);

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
    protected function loadElement($key, $value): ?ObjectInterface
    {
        $objects = $this->_flexDirectory->loadObjects([$key => $value]);

        return $objects ? reset($objects): null;
    }

    /**
     * @param array|null $entries
     * @return ObjectInterface[]
     */
    protected function loadElements(array $entries = null): array
    {
        return $this->_flexDirectory->loadObjects($entries ?? $this->getEntries());
    }

    /**
     * @param array|null $entries
     * @return ObjectCollectionInterface
     */
    protected function loadCollection(array $entries = null): CollectionInterface
    {
        return $this->_flexDirectory->loadCollection($entries ?? $this->getEntries(), $this->_keyField);
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
        return $object->getTimestamp();
    }

    /**
     * @param FlexStorageInterface $storage
     * @param array $index      Saved index
     * @param array $entries    Updated index
     * @return array            Compiled list of entries
     */
    protected static function updateIndexFile(FlexStorageInterface $storage, array $index, array $entries): array
    {
        // Calculate removed objects.
        $removed = array_diff_key($index, $entries);

        // First get rid of all removed objects.
        if ($removed) {
            $index = array_diff_key($index, $removed);
        }

        if ($entries) {
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
        $indexFile = static::getIndexFile($storage);
        $indexFile->lock();

        // Read all the data rows into an array.
        $keys = array_fill_keys(array_keys($entries), null);
        $rows = $storage->readRows($keys);

        $keyField = $storage->getKeyField();

        // Go through all the updated objects and refresh their index data.
        $updated = $added = [];
        foreach ($rows as $key => $row) {
            if (null !== $row) {
                $entry = ['key' => $key] + $entries[$key];
                if ($keyField !== 'storage_key' && isset($row[$keyField])) {
                    $entry['key'] = $row[$keyField];
                }
                static::updateIndexData($entry, $row);
                if (isset($row['__error'])) {
                    $entry['__error'] = true;
                    static::onException(new \RuntimeException(sprintf('Object failed to load: %s (%s)', $key, $row['__error'])));
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

        // Sort the index before saving it.
        ksort($index, SORT_NATURAL);

        static::onChanges($index, $added, $updated, $removed);

        $indexFile->save(['count' => \count($index), 'index' => $index]);
        $indexFile->unlock();

        return $index;
    }

    protected static function updateIndexData(array &$entry, array $data)
    {
    }

    protected static function loadEntriesFromIndex(FlexStorageInterface $storage)
    {
        $indexFile = static::getIndexFile($storage);

        $data = [];
        try {
            $data = (array)$indexFile->content();
        } catch (\Exception $e) {
            $e = new \RuntimeException(sprintf('Index failed to load: %s', $e->getMessage()), $e->getCode(), $e);

            static::onException($e);
        }

        return $data['index'] ?? [];
    }

    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = $locator->findResource($storage->getStoragePath() . '/index.yaml', true, true);

        return CompiledYamlFile::instance($filename);
    }

    protected static function onException(\Exception $e)
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

    protected static function onChanges(array $entries, array $added, array $updated, array $removed)
    {
        $message = sprintf('Index updated, %d objects (%d added, %d updated, %d removed).', \count($entries), \count($added), \count($updated), \count($removed));

        $grav = Grav::instance();

        /** @var Logger $logger */
        $logger = $grav['log'];
        $logger->addDebug($message);

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->addMessage($message, 'debug');
    }

    public function __debugInfo()
    {
        return [
            'type:private' => $this->getFlexType(),
            'key:private' => $this->getKey(),
            'entries_key:private' => $this->getKeyField(),
            'entries:private' => $this->getEntries()
        ];
    }
}
