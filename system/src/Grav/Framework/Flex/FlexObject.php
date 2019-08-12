<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Data\Blueprint;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Common\Utils;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;
use Grav\Framework\Object\Access\NestedArrayAccessTrait;
use Grav\Framework\Object\Access\NestedPropertyTrait;
use Grav\Framework\Object\Access\OverloadedPropertyTrait;
use Grav\Framework\Object\Base\ObjectTrait;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectInterface;
use Grav\Framework\Object\Property\LazyPropertyTrait;
use Psr\SimpleCache\InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Template;
use Twig\TemplateWrapper;

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

    /** @var FlexDirectory */
    private $_flexDirectory;
    /** @var FlexFormInterface[] */
    private $_forms = [];
    /** @var array */
    private $_storage;
    /** @var array */
    protected $_changes;

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
            'getCacheKey' => true,
            'getCacheChecksum' => true,
            'getTimestamp' => true,
            'value' => true,
            'exists' => true,
            'hasProperty' => true,
            'getProperty' => true,

            // FlexAclTrait
            'isAuthorized' => 'session',
        ];
    }

    public static function createFromStorage(array $elements, array $storage, FlexDirectory $directory, bool $validate = false)
    {
        $instance = new static($elements, $storage['key'], $directory, $validate);
        $instance->setStorage($storage);

        return $instance;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::__construct()
     */
    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false)
    {
        $this->_flexDirectory = $directory;

        if ($validate) {
            $blueprint = $this->getFlexDirectory()->getBlueprint();

            $blueprint->validate($elements);

            $elements = $blueprint->filter($elements);
        }

        $this->filterElements($elements);

        $this->objectConstruct($elements, $key);
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
     * {@inheritdoc}
     * @see FlexObjectInterface::getTimestamp()
     */
    public function getTimestamp(): int
    {
        return $this->_storage['storage_timestamp'] ?? 0;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getCacheKey()
     */
    public function getCacheKey(): string
    {
        return $this->getTypePrefix() . $this->getFlexType() . '.' . $this->getStorageKey();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getCacheChecksum()
     */
    public function getCacheChecksum(): string
    {
        return (string)$this->getTimestamp();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::search()
     */
    public function search(string $search, $properties = null, array $options = null): float
    {
        $options = $options ?? $this->getFlexDirectory()->getConfig('data.search.options', []);
        $properties = $properties ?? $this->getFlexDirectory()->getConfig('data.search.fields', []);
        if (!$properties) {
            foreach ($this->getFlexDirectory()->getConfig('admin.list.fields', []) as $property => $value) {
                if (!empty($value['link'])) {
                    $properties[] = $property;
                }
            }
        }

        $weight = 0;
        foreach ((array)$properties as $property) {
            $weight += $this->searchNestedProperty($property, $search, $options);
        }

        return $weight > 0 ? min($weight, 1) : 0;
    }

    /**
     * {@inheritdoc}
     * @see ObjectInterface::getFlexKey()
     */
    public function getKey()
    {
        return $this->_key ?: $this->getFlexType() . '@@' . spl_object_hash($this);
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getFlexKey()
     */
    public function getFlexKey(): string
    {
        return $this->_storage['flex_key'] ?? $this->_flexDirectory->getFlexType() . '.obj:' . $this->getStorageKey();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getStorageKey()
     */
    public function getStorageKey(): string
    {
        return $this->_storage['storage_key'] ?? $this->getTypePrefix() . $this->getFlexType() . '@@' . spl_object_hash($this);
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getMetaData()
     */
    public function getMetaData(): array
    {
        return $this->getStorage();
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
        $options = $options ?? $this->getFlexDirectory()->getConfig('data.search.options', []);
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
        $options = $options ?? $this->getFlexDirectory()->getConfig('data.search.options', []);
        $value = $this->getNestedProperty($property);

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
        $search = trim($search);

        if ($search === '') {
            return 0;
        }

        if (!\is_string($value) || $value === '') {
            return 0;
        }

        $tested = false;
        if (($tested |= !empty($options['starts_with'])) && Utils::startsWith($value, $search, $options['case_sensitive'] ?? false)) {
            return (float)$options['starts_with'];
        }
        if (($tested |= !empty($options['ends_with'])) && Utils::endsWith($value, $search, $options['case_sensitive'] ?? false)) {
            return (float)$options['ends_with'];
        }
        if ((!$tested || !empty($options['contains'])) && Utils::contains($value, $search, $options['case_sensitive'] ?? false)) {
            return (float)($options['contains'] ?? 1);
        }

        return 0;
    }

    /**
     * Get any changes based on data sent to update
     *
     * @return array
     */
    public function getChanges(): array
    {
        return $this->_changes ?? [];
    }

    /**
     * @return string
     */
    protected function getTypePrefix(): string
    {
        return 'o.';
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
        $this->_storage['storage_key'] = $key;

        return $this;
    }

    /**
     * @param int $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp = null)
    {
        $this->_storage['storage_timestamp'] = $timestamp ?? time();

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::render()
     */
    public function render(string $layout = null, array $context = [])
    {
        if (null === $layout) {
            $layout = 'default';
        }

        $type = $this->getFlexType();

        $grav = Grav::instance();

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->startTimer('flex-object-' . ($debugKey =  uniqid($type, false)), 'Render Object ' . $type . ' (' . $layout . ')');

        $cache = $key = null;
        foreach ($context as $value) {
            if (!\is_scalar($value)) {
                $key = false;
            }
        }

        if ($key !== false) {
            $key = md5($this->getCacheKey() . '.' . $layout . json_encode($context));
            $cache = $this->getCache('render');
        }

        try {
            $data = $cache && $key ? $cache->get($key) : null;

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
            if ($key === false) {
                $block->disableCache();
            }

            $grav->fireEvent('onFlexObjectRender', new Event([
                'object' => $this,
                'layout' => &$layout,
                'context' => &$context
            ]));

            $output = $this->getTemplate($layout)->render(
                ['grav' => $grav, 'config' => $grav['config'], 'block' => $block, 'object' => $this, 'layout' => $layout] + $context
            );

            if ($debugger->enabled()) {
                $name = $this->getKey() . ' (' . $type . ')';
                $output = "\n<!–– START {$name} object ––>\n{$output}\n<!–– END {$name} object ––>\n";
            }

            $block->setContent($output);

            try {
                $cache && $key && $block->isCached() && $cache->set($key, $block->toArray());
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
        return $this->getElements();
    }

    /**
     * @param string $name
     * @return $this
     */
    public function triggerEvent($name)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::update()
     */
    public function update(array $data, array $files = [])
    {
        if ($data) {
            $blueprint = $this->getBlueprint();

            // Process updated data through the object filters.
            $this->filterElements($data);

            // Get currently stored data.
            $elements = $this->getElements();

            // Merge existing object to the test data to be validated.
            $test = $blueprint->mergeData($elements, $data);

            // Validate and filter elements and throw an error if any issues were found.
            $blueprint->validate($test + ['storage_key' => $this->getStorageKey(), 'timestamp' => $this->getTimestamp()]);
            $data = $blueprint->filter($data, false, true);

            // Finally update the object.
            foreach ($blueprint->flattenData($data) as $key => $value) {
                if ($value === null) {
                    $this->unsetNestedProperty($key);
                } else {
                    $this->setNestedProperty($key, $value);
                }
            }

            // Store the changes
            $this->_changes = Utils::arrayDiffMultidimensional($this->getElements(), $elements);
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
            throw new \RuntimeException('Cannot create new object (Already exists)');
        }

        return $this->save();
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::save()
     */
    public function save()
    {
        $this->triggerEvent('onBeforeSave');

        $result = $this->getFlexDirectory()->getStorage()->replaceRows([$this->getStorageKey() => $this->prepareStorage()]);

        $value = reset($result);
        $storageKey = (string)key($result);
        if ($value && $storageKey) {
            $this->setStorageKey($storageKey);
            if (!$this->hasKey()) {
                $this->setKey($storageKey);
            }
        }

        // FIXME: For some reason locator caching isn't cleared for the file, investigate!
        $locator = Grav::instance()['locator'];
        $locator->clearCache();

        // Make sure that the object exists before continuing (just in case).
        if (!$this->exists()) {
            throw new \RuntimeException('Saving failed: Object does not exist!');
        }

        if (method_exists($this, 'saveUpdatedMedia')) {
            $this->saveUpdatedMedia();
        }

        try {
            $this->getFlexDirectory()->clearCache();
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        } catch (\Exception $e) {
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
        $this->triggerEvent('onBeforeDelete');

        $this->getFlexDirectory()->getStorage()->deleteRows([$this->getStorageKey() => $this->prepareStorage()]);

        try {
            $this->getFlexDirectory()->clearCache();
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        } catch (\Exception $e) {
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
        return $this->_flexDirectory->getBlueprint($name ? '.' . $name : $name);
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getForm()
     */
    public function getForm(string $name = '', array $form = null)
    {
        if (!isset($this->_forms[$name])) {
            $this->_forms[$name] = $this->createFormObject($name, $form);
        }

        return $this->_forms[$name];
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getDefaultValue()
     */
    public function getDefaultValue(string $name, string $separator = null)
    {
        $separator = $separator ?: '.';
        $path = explode($separator, $name) ?: [];
        $offset = array_shift($path) ?? '';

        $current = $this->getDefaultValues();

        if (!isset($current[$offset])) {
            return null;
        }

        $current = $current[$offset];

        while ($path) {
            $offset = array_shift($path);

            if ((\is_array($current) || $current instanceof \ArrayAccess) && isset($current[$offset])) {
                $current = $current[$offset];
            } elseif (\is_object($current) && isset($current->{$offset})) {
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
     * @param string $name
     * @param mixed|null $default
     * @param string|null $separator
     * @return mixed
     *
     * @deprecated 1.6 Use ->getFormValue() method instead.
     */
    public function value($name, $default = null, $separator = null)
    {
        return $this->getFormValue($name, $default, $separator);
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getFlexKey();
    }

    public function __debugInfo()
    {
        return [
            'type:private' => $this->getFlexType(),
            'key:private' => $this->getKey(),
            'elements:private' => $this->getElements(),
            'storage:private' => $this->getStorage()
        ];
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
            'storage' => $this->getStorage()
        ];
    }

    /**
     * @param array $serialized
     */
    protected function doUnserialize(array $serialized): void
    {
        $type = $serialized['type'] ?? 'unknown';

        if (!isset($serialized['key'], $serialized['type'], $serialized['elements'])) {
            throw new \InvalidArgumentException("Cannot unserialize '{$type}': Bad data");
        }

        $grav = Grav::instance();
        /** @var Flex|null $flex */
        $flex = $grav['flex_objects'] ?? null;
        $directory = $flex ? $flex->getDirectory($type) : null;
        if (!$directory) {
            throw new \InvalidArgumentException("Cannot unserialize '{$type}': Not found");
        }
        $this->setFlexDirectory($directory);
        $this->setStorage($serialized['storage']);
        $this->setKey($serialized['key']);
        $this->setElements($serialized['elements']);
    }

    /**
     * @param FlexDirectory $directory
     */
    public function setFlexDirectory(FlexDirectory $directory): void
    {
        $this->_flexDirectory = $directory;
    }
    /**
     * @param array $storage
     */
    protected function setStorage(array $storage) : void
    {
        $this->_storage = $storage;
    }

    /**
     * @return array
     */
    protected function getStorage() : array
    {
        return $this->_storage ?? [];
    }

    /**
     * @param string $type
     * @param string $property
     * @return FlexCollectionInterface
     */
    protected function getCollectionByProperty($type, $property)
    {
        $directory = $this->getRelatedDirectory($type);
        $collection = $directory->getCollection();
        $list = $this->getNestedProperty($property) ?: [];

        /** @var FlexCollection $collection */
        $collection = $collection->filter(function ($object) use ($list) { return \in_array($object->id, $list, true); });

        return $collection;
    }

    /**
     * @param string $type
     * @return FlexDirectory
     * @throws \RuntimeException
     */
    protected function getRelatedDirectory($type): FlexDirectory
    {
        /** @var Flex $flex */
        $flex = Grav::instance()['flex_objects'];
        $directory = $flex->getDirectory($type);
        if (!$directory) {
            throw new \RuntimeException(ucfirst($type). ' directory does not exist!');
        }

        return $directory;
    }

    /**
     * @param string $layout
     * @return Template|TemplateWrapper
     * @throws LoaderError
     * @throws SyntaxError
     */
    protected function getTemplate($layout)
    {
        $grav = Grav::instance();

        /** @var Twig $twig */
        $twig = $grav['twig'];

        try {
            return $twig->twig()->resolveTemplate(
                [
                    "flex-objects/layouts/{$this->getFlexType()}/object/{$layout}.html.twig",
                    "flex-objects/layouts/_default/object/{$layout}.html.twig"
                ]
            );
        } catch (LoaderError $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            return $twig->twig()->resolveTemplate(['flex-objects/layouts/404.html.twig']);
        }
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
        if (!empty($elements['storage_key'])) {
            $this->_storage['storage_key'] = trim($elements['storage_key']);
        }
        if (!empty($elements['storage_timestamp'])) {
            $this->_storage['storage_timestamp'] = (int)$elements['storage_timestamp'];
        }

        unset ($elements['storage_key'], $elements['storage_timestamp'], $elements['_post_entries_save']);
    }

    /**
     * This methods allows you to override form objects in child classes.
     *
     * @param string $name Form name
     * @param array|null $form Form fields
     * @return FlexFormInterface
     */
    protected function createFormObject(string $name, array $form = null)
    {
        return new FlexForm($name, $this, $form);
    }
}
