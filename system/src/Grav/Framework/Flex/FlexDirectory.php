<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Cache\Adapter\DoctrineCache;
use Grav\Framework\Cache\Adapter\MemoryCache;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Collection\CollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Flex\Storage\SimpleStorage;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;

/**
 * Class FlexDirectory
 * @package Grav\Framework\Flex
 */
class FlexDirectory implements FlexAuthorizeInterface
{
    use FlexAuthorizeTrait;

    /** @var string */
    protected $type;
    /** @var string */
    protected $blueprint_file;
    /** @var Blueprint[] */
    protected $blueprints;
    /** @var bool[] */
    protected $blueprints_init;
    /** @var FlexIndex */
    protected $index;
    /** @var FlexCollection */
    protected $collection;
    /** @var bool */
    protected $enabled;
    /** @var array */
    protected $defaults;
    /** @var Config */
    protected $config;
    /** @var object */
    protected $storage;
    /** @var CacheInterface */
    protected $cache;
    /** @var string */
    protected $objectClassName;
    /** @var string */
    protected $collectionClassName;

    /**
     * FlexDirectory constructor.
     * @param string $type
     * @param string $blueprint_file
     * @param array $defaults
     */
    public function __construct(string $type, string $blueprint_file, array $defaults = [])
    {
        $this->type = $type;
        $this->blueprints = [];
        $this->blueprint_file = $blueprint_file;
        $this->defaults = $defaults;
        $this->enabled = !empty($defaults['enabled']);
    }

    /**
     * @return bool
     */
    public function isEnabled() : bool
    {
        return $this->enabled;
    }

    /**
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTitle() : string
    {
        return $this->getBlueprintInternal()->get('title', ucfirst($this->getType()));
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return $this->getBlueprintInternal()->get('description', '');
    }

    /**
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $name = null, $default = null)
    {
        if (null === $this->config) {
            $this->config = new Config(array_merge_recursive($this->getBlueprintInternal()->get('config', []), $this->defaults));
        }

        return null === $name ? $this->config : $this->config->get($name, $default);
    }

    /**
     * @param string $type
     * @param string $context
     * @return Blueprint
     */
    public function getBlueprint(string $type = '', string $context = '') : Blueprint
    {
        $blueprint = $this->getBlueprintInternal($type, $context);

        if (empty($this->blueprints_init[$type])) {
            $this->blueprints_init[$type] = true;

            $blueprint->setScope('object');
            $blueprint->init();
            if (empty($blueprint->fields())) {
                throw new RuntimeException(sprintf('Flex: Blueprint for %s is missing', $this->type));
            }
        }

        return $blueprint;
    }

    /**
     * @param string $view
     * @return string
     */
    public function getBlueprintFile(string $view = '') : string
    {
        $file = $this->blueprint_file;
        if ($view !== '') {
            $file = preg_replace('/\.yaml/', "/{$view}.yaml", $file);
        }

        return $file;
    }

    /**
     * Get collection. In the site this will be filtered by the default filters (published etc).
     *
     * Use $directory->getIndex() if you want unfiltered collection.
     *
     * @param array|null $keys  Array of keys.
     * @return FlexIndex|FlexCollection
     */
    public function getCollection(array $keys = null) : CollectionInterface
    {
        $index = clone $this->getIndex($keys);

        if (!Utils::isAdminPlugin()) {
            $filters = (array)$this->getConfig('site.filter', []);
            foreach ($filters as $filter) {
                $index = $index->{$filter}();
            }
        }

        return $index;
    }

    /**
     * @param string $key
     * @return FlexObject|null
     */
    public function getObject($key) : ?FlexObject
    {
        return $this->getCollection()->get($key);
    }

    /**
     * @param array $data
     * @param string|null $key
     * @param bool $isFullUpdate
     * @return FlexObject
     */
    public function update(array $data, string $key = null, bool $isFullUpdate = false) : FlexObject
    {
        $object = null !== $key ? $this->getIndex()->get($key) : null;

        $storage = $this->getStorage();

        if (null === $object) {
            $object = $this->createObject($data, $key, true);
            $key = $object->getStorageKey();

            if ($key) {
                $rows = $storage->replaceRows([$key => $object->triggerEvent('onSave')->prepareStorage()]);
            } else {
                $rows = $storage->createRows([$object->triggerEvent('onSave')->prepareStorage()]);
            }
        } else {
            $oldKey = $object->getStorageKey();
            $object->update($data, $isFullUpdate);
            $newKey = $object->getStorageKey();

            if ($oldKey !== $newKey) {
                $object->triggerEvent('move');
                $storage->renameRow($oldKey, $newKey);
                // TODO: media support.
            }

            $object->save();
            //$rows = $storage->updateRows([$newKey => $object->triggerEvent('onSave')->prepareStorage()]);
        }

        try {
            $this->clearCache();
        } catch (InvalidArgumentException $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            // Caching failed, but we can ignore that for now.
        }

        /** @var FlexObject $class */
        //$class = $this->getObjectClass();
        //
        //$row = $object;
        //$index = $class::createIndex([key($rows) => time()]);
        //$object = $this->createObject($row, key($index), false);

        return $object;
    }

    /**
     * @param string $key
     * @return FlexObject|null
     */
    public function remove(string $key) : ?FlexObject
    {
        $object = null !== $key ? $this->getIndex()->get($key) : null;
        if (!$object) {
            return null;
        }

        $this->getStorage()->deleteRows([$object->getStorageKey() => $object->triggerEvent('onRemove')->prepareStorage()]);

        try {
            $this->clearCache();
        } catch (InvalidArgumentException $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            // Caching failed, but we can ignore that for now.
        }

        return $object;
    }

    /**
     * @param string|null $namespace
     * @return CacheInterface
     */
    public function getCache(string $namespace = null) : CacheInterface
    {
        $namespace = $namespace ?: 'index';

        if (!isset($this->cache[$namespace])) {
            try {
                $grav = Grav::instance();

                /** @var Cache $gravCache */
                $gravCache = $grav['cache'];
                $config = $this->getConfig('cache.' . $namespace);
                if (empty($config['enabled'])) {
                    throw new \RuntimeException(sprintf('Flex: %s %s cache not enabled', $this->type, $namespace));
                }
                $timeout = $config['timeout'] ?? 60;

                $key = $gravCache->getKey();
                if (Utils::isAdminPlugin()) {
                    $key = substr($key, 0, -1);
                }
                $this->cache[$namespace] = new DoctrineCache($gravCache->getCacheDriver(), 'flex-objects-' . $this->getType() . $key, $timeout);
            } catch (\Exception $e) {
                /** @var Debugger $debugger */
                $debugger = Grav::instance()['debugger'];
                $debugger->addException($e);

                $this->cache[$namespace] = new MemoryCache('flex-objects-' . $this->getType());
            }

            // Disable cache key validation.
            $this->cache[$namespace]->setValidation(false);
        }

        return $this->cache[$namespace];
    }

    /**
     * @return $this
     */
    public function clearCache() : self
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];
        $debugger->addMessage(sprintf('Flex: Clearing all %s cache', $this->type), 'debug');

        $this->getCache('index')->clear();
        $this->getCache('object')->clear();
        $this->getCache('render')->clear();

        return $this;
    }

    /**
     * @param string|null $key
     * @return string
     */
    public function getStorageFolder(string $key = null) : string
    {
        return $this->getStorage()->getStoragePath($key);
    }

    /**
     * @param string|null $key
     * @return string
     */
    public function getMediaFolder(string $key = null) : string
    {
        return $this->getStorage()->getMediaPath($key);
    }

    /**
     * @return FlexStorageInterface
     */
    public function getStorage() : FlexStorageInterface
    {
        if (!$this->storage) {
            $this->storage = $this->createStorage();
        }

        return $this->storage;
    }

    /**
     * Get the full collection of all stored objects.
     *
     * Use $directory->getCollection() if you want a filtered collection.
     *
     * @param array|null $keys  Array of keys.
     * @return FlexIndex|FlexCollection
     * @internal
     */
    public function getIndex(array $keys = null) : CollectionInterface
    {
        $index = clone $this->loadIndex();

        if (null !== $keys) {
            $index = $index->select($keys);
        }

        return $index;
    }

    /**
     * @param array $data
     * @param string $key
     * @param bool $validate
     * @return FlexObject
     */
    public function createObject(array $data, string $key, bool $validate = false) : FlexObject
    {
        $className = $this->objectClassName ?: $this->getObjectClass();

        return new $className($data, $key, $this, $validate);
    }

    /**
     * @param array $entries
     * @return FlexCollection
     */
    public function createCollection(array $entries) : FlexCollection
    {
        $className = $this->collectionClassName ?: $this->getCollectionClass();

        return new $className($entries, $this);
    }

    /**
     * @return string
     */
    public function getObjectClass() : string
    {
        if (!$this->objectClassName) {
            $this->objectClassName = $this->getConfig('data.object', 'Grav\\Plugin\\FlexObjects\\FlexObject');
        }
        return $this->objectClassName;

    }

    /**
     * @return string
     */
    public function getCollectionClass() : string
    {
        if (!$this->collectionClassName) {
            $this->collectionClassName = $this->getConfig('data.collection', 'Grav\\Plugin\\FlexObjects\\FlexCollection');
        }
        return $this->collectionClassName;
    }

    /**
     * @param array $entries
     * @return FlexCollection
     */
    public function loadCollection(array $entries) : FlexCollection
    {
        return $this->createCollection($this->loadObjects($entries));
    }

    /**
     * @param array $entries
     * @return FlexObject[]
     */
    public function loadObjects(array $entries) : array
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];
        $debugger->startTimer('flex-objects', sprintf('Flex: Initializing %d %s', \count($entries), $this->type));

        $storage = $this->getStorage();
        $cache = $this->getCache('object');

        // Get storage keys for the objects.
        $keys = [];
        $rows = [];
        foreach ($entries as $key => $value) {
            $k = \is_array($value) ? $value[0] : $key;
            $keys[$k] = $key;
            $rows[$k] = null;
        }

        // Fetch rows from the cache.
        try {
            $rows = $cache->getMultiple(array_keys($rows));
        } catch (InvalidArgumentException $e) {
            $debugger->addException($e);
        }

        // Read missing rows from the storage.
        $updated = [];
        $rows = $storage->readRows($rows, $updated);

        // Store updated rows to the cache.
        if ($updated) {
            try {
                $debugger->addMessage(sprintf('Flex: Caching %d %s: %s', \count($updated), $this->type, implode(', ', array_keys($updated))), 'debug');
                $cache->setMultiple($updated);
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);

                // TODO: log about the issue.
            }
        }

        // Create objects from the rows.
        $list = [];
        foreach ($rows as $storageKey => $row) {
            if ($row === null) {
                $debugger->addMessage(sprintf('Flex: Object %s was not found from %s storage', $storageKey, $this->type), 'debug');
                continue;
            }
            $row += [
                'storage_key' => $storageKey,
                'storage_timestamp' => $entries[$key][1] ?? $entries[$key],
            ];
            $key = $keys[$storageKey];
            $object = $this->createObject($row, $key, false);
            $list[$key] = $object;
        }

        $debugger->stopTimer('flex-objects');

        return $list;
    }

    /**
     * @param string $type_view
     * @param string $context
     * @return Blueprint
     */
    protected function getBlueprintInternal(string $type_view = '', string $context = '') : Blueprint
    {
        if (!isset($this->blueprints[$type_view])) {
            if (!file_exists($this->blueprint_file)) {
                throw new RuntimeException(sprintf('Flex: Blueprint file for %s is missing', $this->type));
            }

            $parts = explode('.', rtrim($type_view, '.'), 2);
            $type = array_shift($parts);
            $view = array_shift($parts) ?: '';

            $blueprint = new Blueprint($this->getBlueprintFile($view));
            if ($context) {
                $blueprint->setContext($context);
            }

            $blueprint->load($type ?: null);
            if ($blueprint->get('type') === 'flex-objects' && isset(Grav::instance()['admin'])) {
                $blueprintBase = (new Blueprint('plugin://flex-objects/blueprints/flex-objects.yaml'))->load();
                $blueprint->extend($blueprintBase, true);
            }

            $this->blueprints[$type_view] = $blueprint;
        }

        return $this->blueprints[$type_view];
    }

    /**
     * @return FlexStorageInterface
     */
    protected function createStorage() : FlexStorageInterface
    {
        $this->collection = $this->createCollection([]);

        $storage = $this->getConfig('data.storage');

        if (!\is_array($storage)) {
            $storage = ['options' => ['folder' => $storage]];
        }

        $className = $storage['class'] ?? SimpleStorage::class;
        $options = $storage['options'] ?? [];

        return new $className($options);
    }

    /**
     * @return FlexIndex|FlexCollection
     */
    protected function loadIndex() : CollectionInterface
    {
        static $i = 0;

        if (null === $this->index) {
            $i++; $j = $i;
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->startTimer('flex-keys-' . $this->type . $j, "Flex: Loading {$this->type} index");

            $storage = $this->getStorage();
            $cache = $this->getCache('index');

            try {
                $keys = $cache->get('__keys');
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);
                $keys = null;
            }

            if (null === $keys) {
                /** @var FlexObject $className */
                $className = $this->getObjectClass();
                $keys = $className::createIndex($storage->getExistingKeys());
                $debugger->addMessage(sprintf('Flex: Caching %s index of %d objects', $this->type, \count($keys)), 'debug');
                try {
                    $cache->set('__keys', $keys);
                } catch (InvalidArgumentException $e) {
                    $debugger->addException($e);
                    // TODO: log about the issue.
                }
            }

            // We need to do this in two steps as orderBy() calls loadIndex() again and we do not want infinite loop.
            $this->index = new FlexIndex($keys, $this);
            $this->index = $this->index->orderBy($this->getConfig('data.ordering', []));

            $debugger->stopTimer('flex-keys-' . $this->type . $j);
        }

        return $this->index;
    }
}
