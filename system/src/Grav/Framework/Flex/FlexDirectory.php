<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Exception;
use Grav\Common\Cache;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Cache\Adapter\DoctrineCache;
use Grav\Framework\Cache\Adapter\MemoryCache;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexDirectoryInterface;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Flex\Storage\SimpleStorage;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;
use Psr\SimpleCache\InvalidArgumentException;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function call_user_func_array;
use function count;
use function is_array;
use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Flex\Types\Generic\GenericCollection;
use Grav\Common\Flex\Types\Generic\GenericIndex;
use function is_callable;

/**
 * Class FlexDirectory
 * @package Grav\Framework\Flex
 */
class FlexDirectory implements FlexDirectoryInterface
{
    use FlexAuthorizeTrait;

    /** @var string */
    protected $type;
    /** @var string */
    protected $blueprint_file;
    /** @var Blueprint[] */
    protected $blueprints;
    /**
     * @var FlexIndexInterface[]
     * @phpstan-var FlexIndexInterface<FlexObjectInterface>[]
     */
    protected $indexes = [];
    /**
     * @var FlexCollectionInterface|null
     * @phpstan-var FlexCollectionInterface<FlexObjectInterface>|null
     */
    protected $collection;
    /** @var bool */
    protected $enabled;
    /** @var array */
    protected $defaults;
    /** @var Config */
    protected $config;
    /** @var FlexStorageInterface */
    protected $storage;
    /** @var CacheInterface[] */
    protected $cache;
    /** @var FlexObjectInterface[] */
    protected $objects;
    /** @var string */
    protected $objectClassName;
    /** @var string */
    protected $collectionClassName;
    /** @var string */
    protected $indexClassName;

    /** @var string|null */
    private $_authorize;

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
        $this->objects = [];
    }

    /**
     * @return bool
     */
    public function isListed(): bool
    {
        $grav = Grav::instance();

        /** @var Flex $flex */
        $flex = $grav['flex'];
        $directory = $flex->getDirectory($this->type);

        return null !== $directory;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return string
     */
    public function getFlexType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getBlueprintInternal()->get('title', ucfirst($this->getFlexType()));
    }

    /**
     * @return string
     */
    public function getDescription(): string
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
            $config = $this->getBlueprintInternal()->get('config', []);
            $config = is_array($config) ? array_replace_recursive($config, $this->defaults, $this->getDirectoryConfig($config['admin']['views']['configure']['form'] ?? $config['admin']['configure']['form'] ?? null)) : null;
            if (!is_array($config)) {
                throw new RuntimeException('Bad configuration');
            }

            $this->config = new Config($config);
        }

        return null === $name ? $this->config : $this->config->get($name, $default);
    }

    /**
     * @param string|string[]|null $properties
     * @return array
     */
    public function getSearchProperties($properties = null): array
    {
        if (null !== $properties) {
            return (array)$properties;
        }

        $properties = $this->getConfig('data.search.fields');
        if (!$properties) {
            $fields = $this->getConfig('admin.views.list.fields') ?? $this->getConfig('admin.list.fields', []);
            foreach ($fields as $property => $value) {
                if (!empty($value['link'])) {
                    $properties[] = $property;
                }
            }
        }

        return $properties;
    }

    /**
     * @param array|null $options
     * @return array
     */
    public function getSearchOptions(array $options = null): array
    {
        if (empty($options['merge'])) {
            return $options ?? (array)$this->getConfig('data.search.options');
        }

        unset($options['merge']);

        return $options + (array)$this->getConfig('data.search.options');
    }

    /**
     * @param string|null $name
     * @param array $options
     * @return FlexFormInterface
     * @internal
     */
    public function getDirectoryForm(string $name = null, array $options = [])
    {
        $name = $name ?: $this->getConfig('admin.views.configure.form', '') ?: $this->getConfig('admin.configure.form', '');

        return new FlexDirectoryForm($name ?? '', $this, $options);
    }

    /**
     * @return Blueprint
     * @internal
     */
    public function getDirectoryBlueprint()
    {
        $name = 'configure';

        $type = $this->getBlueprint();
        $overrides = $type->get("blueprints/{$name}");

        $path = "blueprints://flex/shared/{$name}.yaml";
        $blueprint = new Blueprint($path);
        $blueprint->load();
        if (isset($overrides['fields'])) {
            $blueprint->embed('form/fields/tabs/fields', $overrides['fields']);
        }
        $blueprint->init();

        return $blueprint;
    }

    /**
     * @param string $name
     * @param array $data
     * @return void
     * @throws Exception
     * @internal
     */
    public function saveDirectoryConfig(string $name, array $data)
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $filename = $this->getDirectoryConfigUri($name);
        if (file_exists($filename)) {
            $filename = $locator->findResource($filename, true);
        } else {
            $filesystem = Filesystem::getInstance();
            $dirname = $filesystem->dirname($filename);
            $basename = $filesystem->basename($filename);
            $dirname = $locator->findResource($dirname, true) ?: $locator->findResource($dirname, true, true);
            $filename = "{$dirname}/{$basename}";
        }

        $file = YamlFile::instance($filename);
        if (!empty($data)) {
            $file->save($data);
        } else {
            $file->delete();
        }
    }

    /**
     * @param string $name
     * @return array
     * @internal
     */
    public function loadDirectoryConfig(string $name): array
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $uri = $this->getDirectoryConfigUri($name);

        // If configuration is found in main configuration, use it.
        if (str_starts_with($uri, 'config://')) {
            $path = str_replace('/', '.', substr($uri, 9, -5));

            return (array)$grav['config']->get($path);
        }

        // Load the configuration file.
        $filename = $locator->findResource($uri, true);
        if ($filename === false) {
            return [];
        }

        $file = YamlFile::instance($filename);

        return $file->content();
    }

    /**
     * @param string|null $name
     * @return string
     */
    public function getDirectoryConfigUri(string $name = null): string
    {
        $name = $name ?: $this->getFlexType();
        $blueprint = $this->getBlueprint();

        return $blueprint->get('blueprints/views/configure/file') ?? $blueprint->get('blueprints/configure/file') ?? "config://flex/{$name}.yaml";
    }

    /**
     * @param string|null $name
     * @return array
     */
    protected function getDirectoryConfig(string $name = null): array
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];
        $name = $name ?: $this->getFlexType();

        return $config->get("flex.{$name}", []);
    }

    /**
     * Returns a new uninitialized instance of blueprint.
     *
     * Always use $object->getBlueprint() or $object->getForm()->getBlueprint() instead.
     *
     * @param string $type
     * @param string $context
     * @return Blueprint
     */
    public function getBlueprint(string $type = '', string $context = '')
    {
        return clone $this->getBlueprintInternal($type, $context);
    }

    /**
     * @param string $view
     * @return string
     */
    public function getBlueprintFile(string $view = ''): string
    {
        $file = $this->blueprint_file;
        if ($view !== '') {
            $file = preg_replace('/\.yaml/', "/{$view}.yaml", $file);
        }

        return (string)$file;
    }

    /**
     * Get collection. In the site this will be filtered by the default filters (published etc).
     *
     * Use $directory->getIndex() if you want unfiltered collection.
     *
     * @param array|null $keys  Array of keys.
     * @param string|null $keyField  Field to be used as the key.
     * @return FlexCollectionInterface
     * @phpstan-return FlexCollectionInterface<FlexObjectInterface>
     */
    public function getCollection(array $keys = null, string $keyField = null): FlexCollectionInterface
    {
        // Get all selected entries.
        $index = $this->getIndex($keys, $keyField);

        if (!Utils::isAdminPlugin()) {
            // If not in admin, filter the list by using default filters.
            $filters = (array)$this->getConfig('site.filter', []);

            foreach ($filters as $filter) {
                $index = $index->{$filter}();
            }
        }

        return $index;
    }

    /**
     * Get the full collection of all stored objects.
     *
     * Use $directory->getCollection() if you want a filtered collection.
     *
     * @param array|null $keys  Array of keys.
     * @param string|null $keyField  Field to be used as the key.
     * @return FlexIndexInterface
     * @phpstan-return FlexIndexInterface<FlexObjectInterface>
     */
    public function getIndex(array $keys = null, string $keyField = null): FlexIndexInterface
    {
        $keyField = $keyField ?? '';
        $index = $this->indexes[$keyField] ?? $this->loadIndex($keyField);
        $index = clone $index;

        if (null !== $keys) {
            /** @var FlexIndexInterface<FlexObjectInterface> $index */
            $index = $index->select($keys);
        }

        return $index->getIndex();
    }

    /**
     * Returns an object if it exists. If no arguments are passed (or both of them are null), method creates a new empty object.
     *
     * Note: It is not safe to use the object without checking if the user can access it.
     *
     * @param string|null $key
     * @param string|null $keyField  Field to be used as the key.
     * @return FlexObjectInterface|null
     */
    public function getObject($key = null, string $keyField = null): ?FlexObjectInterface
    {
        if (null === $key) {
            return $this->createObject([], '');
        }

        $keyField = $keyField ?? '';
        $index = $this->indexes[$keyField] ?? $this->loadIndex($keyField);

        return $index->get($key);
    }

    /**
     * @param string|null $namespace
     * @return CacheInterface
     */
    public function getCache(string $namespace = null)
    {
        $namespace = $namespace ?: 'index';
        $cache = $this->cache[$namespace] ?? null;

        if (null === $cache) {
            try {
                $grav = Grav::instance();

                /** @var Cache $gravCache */
                $gravCache = $grav['cache'];
                $config = $this->getConfig('object.cache.' . $namespace);
                if (empty($config['enabled'])) {
                    $cache = new MemoryCache('flex-objects-' . $this->getFlexType());
                } else {
                    $lifetime = $config['lifetime'] ?? 60;

                    $key = $gravCache->getKey();
                    if (Utils::isAdminPlugin()) {
                        $key = substr($key, 0, -1);
                    }
                    $cache = new DoctrineCache($gravCache->getCacheDriver(), 'flex-objects-' . $this->getFlexType() . $key, $lifetime);
                }
            } catch (Exception $e) {
                /** @var Debugger $debugger */
                $debugger = Grav::instance()['debugger'];
                $debugger->addException($e);

                $cache = new MemoryCache('flex-objects-' . $this->getFlexType());
            }

            // Disable cache key validation.
            $cache->setValidation(false);
            $this->cache[$namespace] = $cache;
        }

        return $cache;
    }

    /**
     * @return $this
     */
    public function clearCache()
    {
        $grav = Grav::instance();

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->addMessage(sprintf('Flex: Clearing all %s cache', $this->type), 'debug');

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $locator->clearCache();

        $this->getCache('index')->clear();
        $this->getCache('object')->clear();
        $this->getCache('render')->clear();

        $this->indexes = [];
        $this->objects = [];

        return $this;
    }

    /**
     * @param string|null $key
     * @return string|null
     */
    public function getStorageFolder(string $key = null): ?string
    {
        return $this->getStorage()->getStoragePath($key);
    }

    /**
     * @param string|null $key
     * @return string|null
     */
    public function getMediaFolder(string $key = null): ?string
    {
        return $this->getStorage()->getMediaPath($key);
    }

    /**
     * @return FlexStorageInterface
     */
    public function getStorage(): FlexStorageInterface
    {
        if (null === $this->storage) {
            $this->storage = $this->createStorage();
        }

        return $this->storage;
    }

    /**
     * @param array $data
     * @param string $key
     * @param bool $validate
     * @return FlexObjectInterface
     */
    public function createObject(array $data, string $key = '', bool $validate = false): FlexObjectInterface
    {
        /** @phpstan-var class-string $className */
        $className = $this->objectClassName ?: $this->getObjectClass();
        if (!is_a($className, FlexObjectInterface::class, true)) {
            throw new \RuntimeException('Bad object class: ' . $className);
        }

        return new $className($data, $key, $this, $validate);
    }

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return FlexCollectionInterface
     * @phpstan-return FlexCollectionInterface<FlexObjectInterface>
     */
    public function createCollection(array $entries, string $keyField = null): FlexCollectionInterface
    {
        /** phpstan-var class-string $className */
        $className = $this->collectionClassName ?: $this->getCollectionClass();
        if (!is_a($className, FlexCollectionInterface::class, true)) {
            throw new \RuntimeException('Bad collection class: ' . $className);
        }

        return $className::createFromArray($entries, $this, $keyField);
    }

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return FlexIndexInterface
     * @phpstan-return FlexIndexInterface<FlexObjectInterface>
     */
    public function createIndex(array $entries, string $keyField = null): FlexIndexInterface
    {
        /** @phpstan-var class-string $className */
        $className = $this->indexClassName ?: $this->getIndexClass();
        if (!is_a($className, FlexIndexInterface::class, true)) {
            throw new \RuntimeException('Bad index class: ' . $className);
        }

        return $className::createFromArray($entries, $this, $keyField);
    }

    /**
     * @return string
     */
    public function getObjectClass(): string
    {
        if (!$this->objectClassName) {
            $this->objectClassName = $this->getConfig('data.object', GenericObject::class);
        }

        return $this->objectClassName;
    }

    /**
     * @return string
     */
    public function getCollectionClass(): string
    {
        if (!$this->collectionClassName) {
            $this->collectionClassName = $this->getConfig('data.collection', GenericCollection::class);
        }

        return $this->collectionClassName;
    }


    /**
     * @return string
     */
    public function getIndexClass(): string
    {
        if (!$this->indexClassName) {
            $this->indexClassName = $this->getConfig('data.index', GenericIndex::class);
        }

        return $this->indexClassName;
    }

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return FlexCollectionInterface
     * @phpstan-return FlexCollectionInterface<FlexObjectInterface>
     */
    public function loadCollection(array $entries, string $keyField = null): FlexCollectionInterface
    {
        return $this->createCollection($this->loadObjects($entries), $keyField);
    }

    /**
     * @param array $entries
     * @return FlexObjectInterface[]
     * @internal
     */
    public function loadObjects(array $entries): array
    {
        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        $keys = [];
        $rows = [];
        $fetch = [];

        // Build lookup arrays with storage keys for the objects.
        foreach ($entries as $key => $value) {
            $k = $value['storage_key'] ?? '';
            if ($k === '') {
                continue;
            }
            $v = $this->objects[$k] ?? null;
            $keys[$k] = $key;
            $rows[$k] = $v;
            if (!$v) {
                $fetch[] = $k;
            }
        }

        // Attempt to fetch missing rows from the cache.
        if ($fetch) {
             $rows = (array)array_replace($rows, $this->loadCachedObjects($fetch));
        }

        // Read missing rows from the storage.
        $updated = [];
        $storage = $this->getStorage();
        $rows = $storage->readRows($rows, $updated);

        // Create objects from the rows.
        $isListed = $this->isListed();
        $list = [];
        foreach ($rows as $storageKey => $row) {
            $usedKey = $keys[$storageKey];

            if ($row instanceof FlexObjectInterface) {
                $object = $row;
            } else {
                if ($row === null) {
                    $debugger->addMessage(sprintf('Flex: Object %s was not found from %s storage', $storageKey, $this->type), 'debug');
                    continue;
                }

                if (isset($row['__ERROR'])) {
                    $message = sprintf('Flex: Object %s is broken in %s storage: %s', $storageKey, $this->type, $row['__ERROR']);
                    $debugger->addException(new RuntimeException($message));
                    $debugger->addMessage($message, 'error');
                    continue;
                }

                if (!isset($row['__META'])) {
                    $row['__META'] = [
                        'storage_key' => $storageKey,
                        'storage_timestamp' => $entries[$usedKey]['storage_timestamp'] ?? 0,
                    ];
                }

                $key = $row['__META']['key'] ?? $entries[$usedKey]['key'] ?? $usedKey;
                $object = $this->createObject($row, $key, false);
                $this->objects[$storageKey] = $object;
                if ($isListed) {
                    // If unserialize works for the object, serialize the object to speed up the loading.
                    $updated[$storageKey] = $object;
                }
            }

            $list[$usedKey] = $object;
        }

        // Store updated rows to the cache.
        if ($updated) {
            $cache = $this->getCache('object');
            if (!$cache instanceof MemoryCache) {
                ///** @var Debugger $debugger */
                //$debugger = Grav::instance()['debugger'];
                //$debugger->addMessage(sprintf('Flex: Caching %d %s', \count($entries), $this->type), 'debug');
            }
            try {
                $cache->setMultiple($updated);
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);
                // TODO: log about the issue.
            }
        }

        if ($fetch) {
            $debugger->stopTimer('flex-objects');
        }

        return $list;
    }

    protected function loadCachedObjects(array $fetch): array
    {
        if (!$fetch) {
            return [];
        }

        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];

        $cache = $this->getCache('object');

        // Attempt to fetch missing rows from the cache.
        $fetched = [];
        try {
            $loading = count($fetch);

            $debugger->startTimer('flex-objects', sprintf('Flex: Loading %d %s', $loading, $this->type));

            $fetched = (array)$cache->getMultiple($fetch);
            if ($fetched) {
                $index = $this->loadIndex('storage_key');

                // Make sure cached objects are up to date: compare against index checksum/timestamp.
                /**
                 * @var string $key
                 * @var mixed $value
                 */
                foreach ($fetched as $key => $value) {
                    if ($value instanceof FlexObjectInterface) {
                        $objectMeta = $value->getMetaData();
                    } else {
                        $objectMeta = $value['__META'] ?? [];
                    }
                    $indexMeta = $index->getMetaData($key);

                    $indexChecksum = $indexMeta['checksum'] ?? $indexMeta['storage_timestamp'] ?? null;
                    $objectChecksum = $objectMeta['checksum'] ?? $objectMeta['storage_timestamp'] ?? null;
                    if ($indexChecksum !== $objectChecksum) {
                        unset($fetched[$key]);
                    }
                }
            }

        } catch (InvalidArgumentException $e) {
            $debugger->addException($e);
        }

        return $fetched;
    }

    /**
     * @return void
     */
    public function reloadIndex(): void
    {
        $this->getCache('index')->clear();
        $this->getIndex()::loadEntriesFromStorage($this->getStorage());

        $this->indexes = [];
        $this->objects = [];
    }

    /**
     * @param string $scope
     * @param string $action
     * @return string
     */
    public function getAuthorizeRule(string $scope, string $action): string
    {
        if (!$this->_authorize) {
            $config = $this->getConfig('admin.permissions');
            if ($config) {
                $this->_authorize = array_key_first($config) . '.%2$s';
            } else {
                $this->_authorize = '%1$s.flex-object.%2$s';
            }
        }

        return sprintf($this->_authorize, $scope, $action);
    }

    /**
     * @param string $type_view
     * @param string $context
     * @return Blueprint
     */
    protected function getBlueprintInternal(string $type_view = '', string $context = '')
    {
        if (!isset($this->blueprints[$type_view])) {
            if (!file_exists($this->blueprint_file)) {
                throw new RuntimeException(sprintf('Flex: Blueprint file for %s is missing', $this->type));
            }

            $parts = explode('.', rtrim($type_view, '.'), 2);
            $type = array_shift($parts);
            $view = array_shift($parts) ?: '';

            $blueprint = new Blueprint($this->getBlueprintFile($view));
            $blueprint->addDynamicHandler('data', function (array &$field, $property, array &$call) {
                $this->dynamicDataField($field, $property, $call);
            });
            $blueprint->addDynamicHandler('flex', function (array &$field, $property, array &$call) {
                $this->dynamicFlexField($field, $property, $call);
            });
            $blueprint->addDynamicHandler('authorize', function (array &$field, $property, array &$call) {
                $this->dynamicAuthorizeField($field, $property, $call);
            });

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
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicDataField(array &$field, $property, array $call)
    {
        $params = $call['params'];
        if (is_array($params)) {
            $function = array_shift($params);
        } else {
            $function = $params;
            $params = [];
        }

        $object = $call['object'];
        if ($function === '\Grav\Common\Page\Pages::pageTypes') {
            $params = [$object instanceof PageInterface && $object->isModule() ? 'modular' : 'standard'];
        }

        $data = null;
        if (is_callable($function)) {
            $data = call_user_func_array($function, $params);
        }

        // If function returns a value,
        if (null !== $data) {
            if (is_array($data) && isset($field[$property]) && is_array($field[$property])) {
                // Combine field and @data-field together.
                $field[$property] += $data;
            } else {
                // Or create/replace field with @data-field.
                $field[$property] = $data;
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicFlexField(array &$field, $property, array $call): void
    {
        $params = (array)$call['params'];
        $object = $call['object'] ?? null;
        $method = array_shift($params);
        $not = false;
        if (str_starts_with($method, '!')) {
            $method = substr($method, 1);
            $not = true;
        } elseif (str_starts_with($method, 'not ')) {
            $method = substr($method, 4);
            $not = true;
        }
        $method = trim($method);

        if ($object && method_exists($object, $method)) {
            $value = $object->{$method}(...$params);
            if (is_array($value) && isset($field[$property]) && is_array($field[$property])) {
                $value = $this->mergeArrays($field[$property], $value);
            }
            $value = $not ? !$value : $value;

            if ($property === 'ignore' && $value) {
                Blueprint::addPropertyRecursive($field, 'validate', ['ignore' => true]);
            } else {
                $field[$property] = $value;
            }
        }
    }

    /**
     * @param array $field
     * @param string $property
     * @param array $call
     * @return void
     */
    protected function dynamicAuthorizeField(array &$field, $property, array $call): void
    {
        $params = (array)$call['params'];
        $object = $call['object'] ?? null;
        $permission = array_shift($params);
        $not = false;
        if (str_starts_with($permission, '!')) {
            $permission = substr($permission, 1);
            $not = true;
        } elseif (str_starts_with($permission, 'not ')) {
            $permission = substr($permission, 4);
            $not = true;
        }
        $permission = trim($permission);

        if ($object) {
            $value = $object->isAuthorized($permission) ?? false;

            $field[$property] = $not ? !$value : $value;
        }
    }

    /**
     * @param array $array1
     * @param array $array2
     * @return array
     */
    protected function mergeArrays(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->mergeArrays($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }

    /**
     * @return FlexStorageInterface
     */
    protected function createStorage(): FlexStorageInterface
    {
        $this->collection = $this->createCollection([]);

        $storage = $this->getConfig('data.storage');

        if (!is_array($storage)) {
            $storage = ['options' => ['folder' => $storage]];
        }

        $className = $storage['class'] ?? SimpleStorage::class;
        $options = $storage['options'] ?? [];

        if (!is_a($className, FlexStorageInterface::class, true)) {
            throw new \RuntimeException('Bad storage class: ' . $className);
        }

        return new $className($options);
    }

    /**
     * @param string $keyField
     * @return FlexIndexInterface
     * @phpstan-return FlexIndexInterface<FlexObjectInterface>
     */
    protected function loadIndex(string $keyField): FlexIndexInterface
    {
        static $i = 0;

        $index = $this->indexes[$keyField] ?? null;
        if (null !== $index) {
            return $index;
        }

        $index = $this->indexes['storage_key'] ?? null;
        if (null === $index) {
            $i++;
            $j = $i;
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

            if (!is_array($keys)) {
                /** @phpstan-var class-string $className */
                $className = $this->getIndexClass();
                $keys = $className::loadEntriesFromStorage($storage);
                if (!$cache instanceof MemoryCache) {
                    $debugger->addMessage(
                        sprintf('Flex: Caching %s index of %d objects', $this->type, count($keys)),
                        'debug'
                    );
                }
                try {
                    $cache->set('__keys', $keys);
                } catch (InvalidArgumentException $e) {
                    $debugger->addException($e);
                    // TODO: log about the issue.
                }
            }

            $ordering = $this->getConfig('data.ordering', []);

            // We need to do this in two steps as orderBy() calls loadIndex() again and we do not want infinite loop.
            $this->indexes['storage_key'] = $index = $this->createIndex($keys, 'storage_key');
            if ($ordering) {
                /** @var FlexCollectionInterface<FlexObjectInterface> $collection */
                $collection = $this->indexes['storage_key']->orderBy($ordering);
                $this->indexes['storage_key'] = $index = $collection->getIndex();
            }

            $debugger->stopTimer('flex-keys-' . $this->type . $j);
        }

        if ($keyField !== 'storage_key') {
            $this->indexes[$keyField] = $index = $index->withKeyField($keyField ?: null);
        }

        return $index;
    }

    /**
     * @param string $action
     * @return string
     */
    protected function getAuthorizeAction(string $action): string
    {
        // Handle special action save, which can mean either update or create.
        if ($action === 'save') {
            $action = 'create';
        }

        return $action;
    }
    /**
     * @return UserInterface|null
     */
    protected function getActiveUser(): ?UserInterface
    {
        /** @var UserInterface|null $user */
        $user = Grav::instance()['user'] ?? null;

        return $user;
    }

    /**
     * @return string
     */
    protected function getAuthorizeScope(): string
    {
        return isset(Grav::instance()['admin']) ? 'admin' : 'site';
    }

    // DEPRECATED METHODS

    /**
     * @return string
     * @deprecated 1.6 Use ->getFlexType() method instead.
     */
    public function getType(): string
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use ->getFlexType() method instead', E_USER_DEPRECATED);

        return $this->type;
    }

    /**
     * @param array $data
     * @param string|null $key
     * @return FlexObjectInterface
     * @deprecated 1.7 Use $object->update()->save() instead.
     */
    public function update(array $data, string $key = null): FlexObjectInterface
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() should not be used anymore: use $object->update()->save() instead.', E_USER_DEPRECATED);

        $object = null !== $key ? $this->getIndex()->get($key): null;

        $storage = $this->getStorage();

        if (null === $object) {
            $object = $this->createObject($data, $key ?? '', true);
            $key = $object->getStorageKey();

            if ($key) {
                $storage->replaceRows([$key => $object->prepareStorage()]);
            } else {
                $storage->createRows([$object->prepareStorage()]);
            }
        } else {
            $oldKey = $object->getStorageKey();
            $object->update($data);
            $newKey = $object->getStorageKey();

            if ($oldKey !== $newKey) {
                if (method_exists($object, 'triggerEvent')) {
                    $object->triggerEvent('move');
                }
                $storage->renameRow($oldKey, $newKey);
                // TODO: media support.
            }

            $object->save();
        }

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
     * @param string $key
     * @return FlexObjectInterface|null
     * @deprecated 1.7 Use $object->delete() instead.
     */
    public function remove(string $key): ?FlexObjectInterface
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() should not be used anymore: use $object->delete() instead.', E_USER_DEPRECATED);

        $object = $this->getIndex()->get($key);
        if (!$object) {
            return null;
        }

        $object->delete();

        return $object;
    }
}
