<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use ArrayAccess;
use Exception;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Twig\Twig;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;
use Grav\Framework\Flex\Traits\FlexRelatedDirectoryTrait;
use Grav\Framework\Object\Access\NestedArrayAccessTrait;
use Grav\Framework\Object\Access\NestedPropertyTrait;
use Grav\Framework\Object\Access\OverloadedPropertyTrait;
use Grav\Framework\Object\Base\ObjectTrait;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Framework\Object\Property\LazyPropertyTrait;
use Psr\SimpleCache\InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Template;
use Twig\TemplateWrapper;
use function get_class;
use function in_array;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function json_encode;

/**
 * Class FlexObject
 * @package Grav\Framework\Flex
 */
class FlexObject implements FlexObjectInterface, FlexAuthorizeInterface
{
    use ObjectTrait;
    use LazyPropertyTrait {
        LazyPropertyTrait::__construct as private objectConstruct;
    }
    use NestedPropertyTrait;
    use OverloadedPropertyTrait;
    use NestedArrayAccessTrait;
    use FlexAuthorizeTrait;
    use FlexRelatedDirectoryTrait;

    /** @var FlexDirectory */
    private $_flexDirectory;
    /** @var FlexFormInterface[] */
    private $_forms = [];
    /** @var Blueprint[] */
    private $_blueprint = [];
    /** @var array|null */
    private $_meta;
    /** @var array|null */
    protected $_original;
    /** @var string|null */
    protected $storage_key;
    /** @var int|null */
    protected $storage_timestamp;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'getTypePrefix' => true,
            'getType' => true,
            'getFlexType' => true,
            'getFlexDirectory' => true,
            'hasFlexFeature' => true,
            'getFlexFeatures' => true,
            'getCacheKey' => true,
            'getCacheChecksum' => false,
            'getTimestamp' => true,
            'value' => true,
            'exists' => true,
            'hasProperty' => true,
            'getProperty' => true,

            // FlexAclTrait
            'isAuthorized' => 'session',
        ];
    }

    /**
     * @param array $elements
     * @param array $storage
     * @param FlexDirectory $directory
     * @param bool $validate
     * @return static
     */
    public static function createFromStorage(array $elements, array $storage, FlexDirectory $directory, bool $validate = false)
    {
        $instance = new static($elements, $storage['key'], $directory, $validate);
        $instance->setMetaData($storage);

        return $instance;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::__construct()
     */
    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false)
    {
        if (get_class($this) === __CLASS__) {
            user_error('Using ' . __CLASS__ . ' directly is deprecated since Grav 1.7, use \Grav\Common\Flex\Types\Generic\GenericObject or your own class instead', E_USER_DEPRECATED);
        }

        $this->_flexDirectory = $directory;

        if (isset($elements['__META'])) {
            $this->setMetaData($elements['__META']);
            unset($elements['__META']);
        }

        if ($validate) {
            $blueprint = $this->getFlexDirectory()->getBlueprint();

            $blueprint->validate($elements, ['xss_check' => false]);

            $elements = $blueprint->filter($elements, true, true);
        }

        $this->filterElements($elements);

        $this->objectConstruct($elements, $key);
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
        $implements = class_implements($this);

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
     * @see FlexObjectInterface::getFlexType()
     */
    public function getFlexType(): string
    {
        return $this->_flexDirectory->getFlexType();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getFlexDirectory()
     */
    public function getFlexDirectory(): FlexDirectory
    {
        return $this->_flexDirectory;
    }

    /**
     * Refresh object from the storage.
     *
     * @param bool $keepMissing
     * @return bool True if the object was refreshed
     */
    public function refresh(bool $keepMissing = false): bool
    {
        $key = $this->getStorageKey();
        if ('' === $key) {
            return false;
        }

        $storage = $this->getFlexDirectory()->getStorage();
        $meta = $storage->getMetaData([$key])[$key] ?? null;

        $newChecksum = $meta['checksum'] ?? $meta['storage_timestamp'] ?? null;
        $curChecksum = $this->_meta['checksum'] ?? $this->_meta['storage_timestamp'] ?? null;

        // Check if object is up to date with the storage.
        if (null === $newChecksum || $newChecksum === $curChecksum) {
            return false;
        }

        // Get current elements (if requested).
        $current = $keepMissing ? $this->getElements() : [];
        // Get elements from the filesystem.
        $elements = $storage->readRows([$key => null])[$key] ?? null;
        if (null !== $elements) {
            $meta = $elements['__META'] ?? $meta;
            unset($elements['__META']);
            $this->filterElements($elements);
            $newKey = $meta['key'] ?? $this->getKey();
            if ($meta) {
                $this->setMetaData($meta);
            }
            $this->objectConstruct($elements, $newKey);

            if ($current) {
                // Inject back elements which are missing in the filesystem.
                $data = $this->getBlueprint()->flattenData($current);
                foreach ($data as $property => $value) {
                    if (strpos($property, '.') === false) {
                        $this->defProperty($property, $value);
                    } else {
                        $this->defNestedProperty($property, $value);
                    }
                }
            }

            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addMessage("Refreshed {$this->getFlexType()} object {$this->getKey()}", 'debug');
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getTimestamp()
     */
    public function getTimestamp(): int
    {
        return $this->_meta['storage_timestamp'] ?? 0;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getCacheKey()
     */
    public function getCacheKey(): string
    {
        return $this->hasKey() ? $this->getTypePrefix() . $this->getFlexType() . '.' . $this->getKey() : '';
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getCacheChecksum()
     */
    public function getCacheChecksum(): string
    {
        return (string)($this->_meta['checksum'] ?? $this->getTimestamp());
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::search()
     */
    public function search(string $search, $properties = null, array $options = null): float
    {
        $directory = $this->getFlexDirectory();
        $properties = $directory->getSearchProperties($properties);
        $options = $directory->getSearchOptions($options);

        $weight = 0;
        foreach ($properties as $property) {
            if (strpos($property, '.')) {
                $weight += $this->searchNestedProperty($property, $search, $options);
            } else {
                $weight += $this->searchProperty($property, $search, $options);
            }
        }

        return $weight > 0 ? min($weight, 1) : 0;
    }

    /**
     * {@inheritdoc}
     * @see ObjectInterface::getFlexKey()
     */
    public function getKey()
    {
        return (string)$this->_key;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getFlexKey()
     */
    public function getFlexKey(): string
    {
        $key = $this->_meta['flex_key'] ?? null;

        if (!$key && $key = $this->getStorageKey()) {
            $key = $this->_flexDirectory->getFlexType() . '.obj:' . $key;
        }

        return (string)$key;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getStorageKey()
     */
    public function getStorageKey(): string
    {
        return (string)($this->storage_key ?? $this->_meta['storage_key'] ?? null);
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getMetaData()
     */
    public function getMetaData(): array
    {
        return $this->_meta ?? [];
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::exists()
     */
    public function exists(): bool
    {
        $key = $this->getStorageKey();

        return $key && $this->getFlexDirectory()->getStorage()->hasKey($key);
    }

    /**
     * @param string $property
     * @param string $search
     * @param array|null $options
     * @return float
     */
    public function searchProperty(string $property, string $search, array $options = null): float
    {
        $options = $options ?? (array)$this->getFlexDirectory()->getConfig('data.search.options');
        $value = $this->getProperty($property);

        return $this->searchValue($property, $value, $search, $options);
    }

    /**
     * @param string $property
     * @param string $search
     * @param array|null $options
     * @return float
     */
    public function searchNestedProperty(string $property, string $search, array $options = null): float
    {
        $options = $options ?? (array)$this->getFlexDirectory()->getConfig('data.search.options');
        if ($property === 'key') {
            $value = $this->getKey();
        } else {
            $value = $this->getNestedProperty($property);
        }

        return $this->searchValue($property, $value, $search, $options);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param string $search
     * @param array|null $options
     * @return float
     */
    protected function searchValue(string $name, $value, string $search, array $options = null): float
    {
        $options = $options ?? [];

        // Ignore empty search strings.
        $search = trim($search);
        if ($search === '') {
            return 0;
        }

        // Search only non-empty string values.
        if (!is_string($value) || $value === '') {
            return 0;
        }

        $caseSensitive = $options['case_sensitive'] ?? false;

        $tested = false;
        if (($tested |= !empty($options['same_as']))) {
            if ($caseSensitive) {
                if ($value === $search) {
                    return (float)$options['same_as'];
                }
            } elseif (mb_strtolower($value) === mb_strtolower($search)) {
                return (float)$options['same_as'];
            }
        }
        if (($tested |= !empty($options['starts_with'])) && Utils::startsWith($value, $search, $caseSensitive)) {
            return (float)$options['starts_with'];
        }
        if (($tested |= !empty($options['ends_with'])) && Utils::endsWith($value, $search, $caseSensitive)) {
            return (float)$options['ends_with'];
        }
        if ((!$tested || !empty($options['contains'])) && Utils::contains($value, $search, $caseSensitive)) {
            return (float)($options['contains'] ?? 1);
        }

        return 0;
    }

    /**
     * Get original data before update
     *
     * @return array
     */
    public function getOriginalData(): array
    {
        return $this->_original ?? [];
    }

    /**
     * Get diff array from the object.
     *
     * @return array
     */
    public function getDiff(): array
    {
        $blueprint = $this->getBlueprint();

        $flattenOriginal = $blueprint->flattenData($this->getOriginalData());
        $flattenElements = $blueprint->flattenData($this->getElements());
        $removedElements = array_diff_key($flattenOriginal, $flattenElements);
        $diff = [];

        // Include all added or changed keys.
        foreach ($flattenElements as $key => $value) {
            $orig = $flattenOriginal[$key] ?? null;
            if ($orig !== $value) {
                $diff[$key] = ['old' => $orig, 'new' => $value];
            }
        }

        // Include all removed keys.
        foreach ($removedElements as $key => $value) {
            $diff[$key] = ['old' => $value, 'new' => null];
        }

        return $diff;
    }

    /**
     * Get any changes from the object.
     *
     * @return array
     */
    public function getChanges(): array
    {
        $diff = $this->getDiff();

        $data = new Data();
        foreach ($diff as $key => $change) {
            $data->set($key, $change['new']);
        }

        return $data->toArray();
    }

    /**
     * @return string
     */
    protected function getTypePrefix(): string
    {
        return 'o.';
    }

    /**
     * Alias of getBlueprint()
     *
     * @return Blueprint
     * @deprecated 1.6 Admin compatibility
     */
    public function blueprints()
    {
        return $this->getBlueprint();
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
     * @param string|null $key
     * @return $this
     */
    public function setStorageKey($key = null)
    {
        $this->storage_key = $key ?? '';

        return $this;
    }

    /**
     * @param int $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp = null)
    {
        $this->storage_timestamp = $timestamp ?? time();

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::render()
     */
    public function render(string $layout = null, array $context = [])
    {
        if (!$layout) {
            $config = $this->getTemplateConfig();
            $layout = $config['object']['defaults']['layout'] ?? 'default';
        }

        $type = $this->getFlexType();

        $grav = Grav::instance();

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->startTimer('flex-object-' . ($debugKey =  uniqid($type, false)), 'Render Object ' . $type . ' (' . $layout . ')');

        $key = $this->getCacheKey();

        // Disable caching if context isn't all scalars.
        if ($key) {
            foreach ($context as $value) {
                if (!is_scalar($value)) {
                    $key = '';
                    break;
                }
            }
        }

        if ($key) {
            // Create a new key which includes layout and context.
            $key = md5($key . '.' . $layout . json_encode($context));
            $cache = $this->getCache('render');
        } else {
            $cache = null;
        }

        try {
            $data = $cache ? $cache->get($key) : null;

            $block = $data ? HtmlBlock::fromArray($data) : null;
        } catch (InvalidArgumentException $e) {
            $debugger->addException($e);

            $block = null;
        } catch (\InvalidArgumentException $e) {
            $debugger->addException($e);

            $block = null;
        }

        $checksum = $this->getCacheChecksum();
        if ($block && $checksum !== $block->getChecksum()) {
            $block = null;
        }

        if (!$block) {
            $block = HtmlBlock::create($key ?: null);
            $block->setChecksum($checksum);
            if (!$cache) {
                $block->disableCache();
            }

            $event = new Event([
                'type' => 'flex',
                'directory' => $this->getFlexDirectory(),
                'object' => $this,
                'layout' => &$layout,
                'context' => &$context
            ]);
            $this->triggerEvent('onRender', $event);

            $output = $this->getTemplate($layout)->render(
                [
                    'grav' => $grav,
                    'config' => $grav['config'],
                    'block' => $block,
                    'directory' => $this->getFlexDirectory(),
                    'object' => $this,
                    'layout' => $layout
                ] + $context
            );

            if ($debugger->enabled()) {
                $name = $this->getKey() . ' (' . $type . ')';
                $output = "\n<!–– START {$name} object ––>\n{$output}\n<!–– END {$name} object ––>\n";
            }

            $block->setContent($output);

            try {
                $cache && $block->isCached() && $cache->set($key, $block->toArray());
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);
            }
        }

        $debugger->stopTimer('flex-object-' . $debugKey);

        return $block;
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getElements();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::prepareStorage()
     */
    public function prepareStorage(): array
    {
        return ['__META' => $this->getMetaData()] + $this->getElements();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::update()
     */
    public function update(array $data, array $files = [])
    {
        if ($data) {
            // Get currently stored data.
            $elements = $this->getElements();

            // Store original version of the object.
            if ($this->_original === null) {
                $this->_original = $elements;
            }

            $blueprint = $this->getBlueprint();

            // Process updated data through the object filters.
            $this->filterElements($data);

            // Merge existing object to the test data to be validated.
            $test = $blueprint->mergeData($elements, $data);

            // Validate and filter elements and throw an error if any issues were found.
            $blueprint->validate($test + ['storage_key' => $this->getStorageKey(), 'timestamp' => $this->getTimestamp()], ['xss_check' => false]);
            $data = $blueprint->filter($data, true, true);

            // Finally update the object.
            $flattenData = $blueprint->flattenData($data);
            foreach ($flattenData as $key => $value) {
                if ($value === null) {
                    $this->unsetNestedProperty($key);
                } else {
                    $this->setNestedProperty($key, $value);
                }
            }
        }

        if ($files && method_exists($this, 'setUpdatedMedia')) {
            $this->setUpdatedMedia($files);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::create()
     */
    public function create(string $key = null)
    {
        if ($key) {
            $this->setStorageKey($key);
        }

        if ($this->exists()) {
            throw new RuntimeException('Cannot create new object (Already exists)');
        }

        return $this->save();
    }

    /**
     * @param string|null $key
     * @return FlexObject|FlexObjectInterface
     */
    public function createCopy(string $key = null)
    {
        $this->markAsCopy();

        return $this->create($key);
    }

    /**
     * @param UserInterface|null $user
     */
    public function check(UserInterface $user = null): void
    {
        // If user has been provided, check if the user has permissions to save this object.
        if ($user && !$this->isAuthorized('save', null, $user)) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::save()
     */
    public function save()
    {
        $this->triggerEvent('onBeforeSave');

        $storage = $this->getFlexDirectory()->getStorage();

        $storageKey = $this->getStorageKey() ?:  '@@' . spl_object_hash($this);

        $result = $storage->replaceRows([$storageKey => $this->prepareStorage()]);

        if (method_exists($this, 'clearMediaCache')) {
            $this->clearMediaCache();
        }

        $value = reset($result);
        $meta = $value['__META'] ?? null;
        if ($meta) {
            /** @phpstan-var class-string $indexClass */
            $indexClass = $this->getFlexDirectory()->getIndexClass();
            $indexClass::updateObjectMeta($meta, $value, $storage);
            $this->_meta = $meta;
        }

        if ($value) {
            $storageKey = $meta['storage_key'] ?? (string)key($result);
            if ($storageKey !== '') {
                $this->setStorageKey($storageKey);
            }

            $newKey = $meta['key'] ?? ($this->hasKey() ? $this->getKey() : null);
            $this->setKey($newKey ?? $storageKey);
        }

        // FIXME: For some reason locator caching isn't cleared for the file, investigate!
        $locator = Grav::instance()['locator'];
        $locator->clearCache();

        if (method_exists($this, 'saveUpdatedMedia')) {
            $this->saveUpdatedMedia();
        }

        try {
            $this->getFlexDirectory()->reloadIndex();
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            // Caching failed, but we can ignore that for now.
        }

        $this->triggerEvent('onAfterSave');

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::delete()
     */
    public function delete()
    {
        if (!$this->exists()) {
            return $this;
        }

        $this->triggerEvent('onBeforeDelete');

        $this->getFlexDirectory()->getStorage()->deleteRows([$this->getStorageKey() => $this->prepareStorage()]);

        try {
            $this->getFlexDirectory()->reloadIndex();
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            // Caching failed, but we can ignore that for now.
        }

        $this->triggerEvent('onAfterDelete');

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getBlueprint()
     */
    public function getBlueprint(string $name = '')
    {
        if (!isset($this->_blueprint[$name])) {
            $blueprint = $this->doGetBlueprint($name);
            $blueprint->setScope('object');
            $blueprint->setObject($this);

            $this->_blueprint[$name] = $blueprint->init();
        }

        return $this->_blueprint[$name];
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getForm()
     */
    public function getForm(string $name = '', array $options = null)
    {
        $hash = $name . '-' . md5(json_encode($options, JSON_THROW_ON_ERROR));
        if (!isset($this->_forms[$hash])) {
            $this->_forms[$hash] = $this->createFormObject($name, $options);
        }

        return $this->_forms[$hash];
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getDefaultValue()
     */
    public function getDefaultValue(string $name, string $separator = null)
    {
        $separator = $separator ?: '.';
        $path = explode($separator, $name);
        $offset = array_shift($path);

        $current = $this->getDefaultValues();

        if (!isset($current[$offset])) {
            return null;
        }

        $current = $current[$offset];

        while ($path) {
            $offset = array_shift($path);

            if ((is_array($current) || $current instanceof ArrayAccess) && isset($current[$offset])) {
                $current = $current[$offset];
            } elseif (is_object($current) && isset($current->{$offset})) {
                $current = $current->{$offset};
            } else {
                return null;
            }
        };

        return $current;
    }

    /**
     * @return array
     */
    public function getDefaultValues(): array
    {
        return $this->getBlueprint()->getDefaults();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getFormValue()
     */
    public function getFormValue(string $name, $default = null, string $separator = null)
    {
        if ($name === 'storage_key') {
            return $this->getStorageKey();
        }
        if ($name === 'storage_timestamp') {
            return $this->getTimestamp();
        }

        return $this->getNestedProperty($name, $default, $separator);
    }

    /**
     * @param FlexDirectory $directory
     */
    public function setFlexDirectory(FlexDirectory $directory): void
    {
        $this->_flexDirectory = $directory;
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString()
    {
        return $this->getFlexKey();
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function __debugInfo()
    {
        return [
            'type:private' => $this->getFlexType(),
            'storage_key:protected' => $this->getStorageKey(),
            'storage_timestamp:protected' => $this->getTimestamp(),
            'key:private' => $this->getKey(),
            'elements:private' => $this->getElements(),
            'storage:private' => $this->getMetaData()
        ];
    }

    /**
     * Clone object.
     */
    #[\ReturnTypeWillChange]
    public function __clone()
    {
        // Allows future compatibility as parent::__clone() works.
    }

    protected function markAsCopy(): void
    {
        $meta = $this->getMetaData();
        $meta['copy'] = true;
        $this->_meta = $meta;
    }

    /**
     * @param string $name
     * @return Blueprint
     */
    protected function doGetBlueprint(string $name = ''): Blueprint
    {
        return $this->_flexDirectory->getBlueprint($name ? '.' . $name : $name);
    }

    /**
     * @param array $meta
     */
    protected function setMetaData(array $meta): void
    {
        $this->_meta = $meta;
    }

    /**
     * @return array
     */
    protected function doSerialize(): array
    {
        return [
            'type' => $this->getFlexType(),
            'key' => $this->getKey(),
            'elements' => $this->getElements(),
            'storage' => $this->getMetaData()
        ];
    }

    /**
     * @param array $serialized
     * @param FlexDirectory|null $directory
     * @return void
     */
    protected function doUnserialize(array $serialized, FlexDirectory $directory = null): void
    {
        $type = $serialized['type'] ?? 'unknown';

        if (!isset($serialized['key'], $serialized['type'], $serialized['elements'])) {
            throw new \InvalidArgumentException("Cannot unserialize '{$type}': Bad data");
        }

        if (null === $directory) {
            $directory = $this->getFlexContainer()->getDirectory($type);
            if (!$directory) {
                throw new \InvalidArgumentException("Cannot unserialize Flex type '{$type}': Directory not found");
            }
        }

        $this->setFlexDirectory($directory);
        $this->setMetaData($serialized['storage']);
        $this->setKey($serialized['key']);
        $this->setElements($serialized['elements']);
    }

    /**
     * @return array
     */
    protected function getTemplateConfig()
    {
        $config = $this->getFlexDirectory()->getConfig('site.templates', []);
        $defaults = array_replace($config['defaults'] ?? [], $config['object']['defaults'] ?? []);
        $config['object']['defaults'] = $defaults;

        return $config;
    }

    /**
     * @param string $layout
     * @return array
     */
    protected function getTemplatePaths(string $layout): array
    {
        $config = $this->getTemplateConfig();
        $type = $this->getFlexType();
        $defaults = $config['object']['defaults'] ?? [];

        $ext = $defaults['ext'] ?? '.html.twig';
        $types = array_unique(array_merge([$type], (array)($defaults['type'] ?? null)));
        $paths = $config['object']['paths'] ?? [
                'flex/{TYPE}/object/{LAYOUT}{EXT}',
                'flex-objects/layouts/{TYPE}/object/{LAYOUT}{EXT}'
            ];
        $table = ['TYPE' => '%1$s', 'LAYOUT' => '%2$s', 'EXT' => '%3$s'];

        $lookups = [];
        foreach ($paths as $path) {
            $path = Utils::simpleTemplate($path, $table);
            foreach ($types as $type) {
                $lookups[] = sprintf($path, $type, $layout, $ext);
            }
        }

        return array_unique($lookups);
    }

    /**
     * Filter data coming to constructor or $this->update() request.
     *
     * NOTE: The incoming data can be an arbitrary array so do not assume anything from its content.
     *
     * @param array $elements
     */
    protected function filterElements(array &$elements): void
    {
        if (isset($elements['storage_key'])) {
            $elements['storage_key'] = trim($elements['storage_key']);
        }
        if (isset($elements['storage_timestamp'])) {
            $elements['storage_timestamp'] = (int)$elements['storage_timestamp'];
        }

        unset($elements['_post_entries_save']);
    }

    /**
     * This methods allows you to override form objects in child classes.
     *
     * @param string $name Form name
     * @param array|null $options Form optiosn
     * @return FlexFormInterface
     */
    protected function createFormObject(string $name, array $options = null)
    {
        return new FlexForm($name, $this, $options);
    }

    /**
     * @param string $action
     * @return string
     */
    protected function getAuthorizeAction(string $action): string
    {
        // Handle special action save, which can mean either update or create.
        if ($action === 'save') {
            $action = $this->exists() ? 'update' : 'create';
        }

        return $action;
    }

    /**
     * Method to reset blueprints if the type changes.
     *
     * @return void
     * @since 1.7.18
     */
    protected function resetBlueprints(): void
    {
        $this->_blueprint = [];
    }

    // DEPRECATED METHODS

    /**
     * @param bool $prefix
     * @return string
     * @deprecated 1.6 Use `->getFlexType()` instead.
     */
    public function getType($prefix = false)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.6, use ->getFlexType() method instead', E_USER_DEPRECATED);

        $type = $prefix ? $this->getTypePrefix() : '';

        return $type . $this->getFlexType();
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @param string|null $separator
     * @return mixed
     *
     * @deprecated 1.6 Use ->getFormValue() method instead.
     */
    public function value($name, $default = null, $separator = null)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.6, use ->getFormValue() method instead', E_USER_DEPRECATED);

        return $this->getFormValue($name, $default, $separator);
    }

    /**
     * @param string $name
     * @param object|null $event
     * @return $this
     * @deprecated 1.7 Moved to \Grav\Common\Flex\Traits\FlexObjectTrait
     */
    public function triggerEvent(string $name, $event = null)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, moved to \Grav\Common\Flex\Traits\FlexObjectTrait', E_USER_DEPRECATED);

        if (null === $event) {
            $event = new Event([
                'type' => 'flex',
                'directory' => $this->getFlexDirectory(),
                'object' => $this
            ]);
        }
        if (strpos($name, 'onFlexObject') !== 0 && strpos($name, 'on') === 0) {
            $name = 'onFlexObject' . substr($name, 2);
        }

        $grav = Grav::instance();
        if ($event instanceof Event) {
            $grav->fireEvent($name, $event);
        } else {
            $grav->dispatchEvent($event);
        }

        return $this;
    }

    /**
     * @param array $storage
     * @deprecated 1.7 Use `->setMetaData()` instead.
     */
    protected function setStorage(array $storage): void
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use ->setMetaData() method instead', E_USER_DEPRECATED);

        $this->setMetaData($storage);
    }

    /**
     * @return array
     * @deprecated 1.7 Use `->getMetaData()` instead.
     */
    protected function getStorage(): array
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, use ->getMetaData() method instead', E_USER_DEPRECATED);

        return $this->getMetaData();
    }

    /**
     * @param string $layout
     * @return Template|TemplateWrapper
     * @throws LoaderError
     * @throws SyntaxError
     * @deprecated 1.7 Moved to \Grav\Common\Flex\Traits\GravTrait
     */
    protected function getTemplate($layout)
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, moved to \Grav\Common\Flex\Traits\GravTrait', E_USER_DEPRECATED);

        $grav = Grav::instance();

        /** @var Twig $twig */
        $twig = $grav['twig'];

        try {
            return $twig->twig()->resolveTemplate($this->getTemplatePaths($layout));
        } catch (LoaderError $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            return $twig->twig()->resolveTemplate(['flex/404.html.twig']);
        }
    }

    /**
     * @return Flex
     * @deprecated 1.7 Moved to \Grav\Common\Flex\Traits\GravTrait
     */
    protected function getFlexContainer(): Flex
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, moved to \Grav\Common\Flex\Traits\GravTrait', E_USER_DEPRECATED);

        /** @var Flex $flex */
        $flex = Grav::instance()['flex'];

        return $flex;
    }

    /**
     * @return UserInterface|null
     * @deprecated 1.7 Moved to \Grav\Common\Flex\Traits\GravTrait
     */
    protected function getActiveUser(): ?UserInterface
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, moved to \Grav\Common\Flex\Traits\GravTrait', E_USER_DEPRECATED);

        /** @var UserInterface|null $user */
        $user = Grav::instance()['user'] ?? null;

        return $user;
    }

    /**
     * @return string
     * @deprecated 1.7 Moved to \Grav\Common\Flex\Traits\GravTrait
     */
    protected function getAuthorizeScope(): string
    {
        user_error(__METHOD__ . '() is deprecated since Grav 1.7, moved to \Grav\Common\Flex\Traits\GravTrait', E_USER_DEPRECATED);

        return isset(Grav::instance()['admin']) ? 'admin' : 'site';
    }
}
