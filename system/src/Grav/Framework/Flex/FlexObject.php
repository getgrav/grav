<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\ValidationException;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Twig\Twig;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Flex\Interfaces\FlexAuthorizeInterface;
use Grav\Framework\Flex\Interfaces\FlexFormInterface;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;
use Grav\Framework\Object\Access\NestedArrayAccessTrait;
use Grav\Framework\Object\Access\NestedPropertyTrait;
use Grav\Framework\Object\Access\OverloadedPropertyTrait;
use Grav\Framework\Object\Base\ObjectTrait;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\Property\LazyPropertyTrait;
use Psr\SimpleCache\InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

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
    /** @var FlexForm[] */
    private $_forms = [];
    /** @var array */
    private $_storage;

    /**
     * @return array
     */
    public static function getCachedMethods()
    {
        return [
            'getTypePrefix' => true,
            'getType' => true,
            'getFlexDirectory' => true,
            'getCacheKey' => true,
            'getCacheChecksum' => true,
            'getTimestamp' => true,
            'value' => true,
            'exists' => true,
            'hasProperty' => true,
            'getProperty' => true,

            // FlexAclTrait
            'authorize' => true,
        ];
    }

    public static function createFromStorage(array $elements, array $storage, FlexDirectory $flexDirectory, $validate = false)
    {
        $instance = new static($elements, $storage['key'], $flexDirectory, $validate);
        $instance->setStorage($storage);

        return $instance;
    }

    /**
     * @param array $elements
     * @param string $key
     * @param FlexDirectory $flexDirectory
     * @param bool $validate
     * @throws \InvalidArgumentException
     * @throws ValidationException
     */
    public function __construct(array $elements, $key, FlexDirectory $flexDirectory, $validate = false)
    {
        $this->_flexDirectory = $flexDirectory;

        if ($validate) {
            $blueprint = $this->getFlexDirectory()->getBlueprint();

            $blueprint->validate($elements);

            $elements = $blueprint->filter($elements);
        }

        $this->filterElements($elements);

        $this->objectConstruct($elements, $key);
    }

    /**
     * @param array $data
     * @param bool $isFullUpdate
     * @return $this
     * @throws ValidationException
     */
    public function update(array $data, $isFullUpdate = false)
    {
        // Validate and filter the incoming data.
        $blueprint = $this->getFlexDirectory()->getBlueprint();

        // Filter updated data.
        $this->filterElements($data);

        if (!$isFullUpdate) {
            // Partial update: merge data to the existing object.
            $elements = $this->getElements();
            $data = $blueprint->mergeData($elements, $data);
        }

        // Validate and filter elements and throw an error if any issues were found.
        $blueprint->validate($data + ['storage_key' => $this->getStorageKey(), 'timestamp' => $this->getTimestamp()]);
        $data = $blueprint->filter($data);

        // Finally update the object.
        $this->setElements($data);

        return $this;
    }

    /**
     * @return string
     */
    protected function getTypePrefix()
    {
        return 'o.';
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
     * @return FlexDirectory
     */
    public function getFlexDirectory() : FlexDirectory
    {
        return $this->_flexDirectory;
    }

    /**
     * @param string $name
     * @return FlexForm
     */
    public function getForm(string $name = '')
    {
        if (!isset($this->_forms[$name])) {
            $this->_forms[$name] = $this->createFormObject($name);
        }

        return $this->_forms[$name];
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint(string $name = '')
    {
        return $this->_flexDirectory->getBlueprint($name ? '.' . $name : $name);
    }

    /**
     * Alias of getBlueprint()
     *
     * @return Blueprint
     * @deprecated Admin compatibility
     */
    public function blueprints()
    {
        return $this->getBlueprint();
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function getFlexKey()
    {
        return $this->_storage['flex_key'] ?? $this->_flexDirectory->getType() . '.obj:' . $this->getStorageKey();
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        return $this->getType(true) . '.' . $this->getStorageKey();
    }

    /**
     * @return string
     */
    public function getCacheChecksum()
    {
        return (string)$this->getTimestamp();
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        return $this->getStorage();
    }

    /**
     * @return string
     */
    public function getStorageKey()
    {
        return $this->_storage['storage_key'] ?? $this->getType() . '@@' . spl_object_hash($this);
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
     * @return int
     */
    public function getTimestamp() : int
    {
        return $this->_storage['storage_timestamp'] ?? 0;
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
     * @param string $layout
     * @param array $context
     * @return HtmlBlock
     * @throws \Exception
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    public function render($layout = null, array $context = [])
    {
        if (null === $layout) {
            $layout = 'default';
        }

        $grav = Grav::instance();

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->startTimer('flex-object-' . ($debugKey =  uniqid($this->getType(false), false)), 'Render Object ' . $this->getType(false) . ' (' . $layout . ')');

        $cache = $key = null;
        foreach ($context as $value) {
            if (!\is_scalar($value)) {
                $key = false;
            }
        }

        if ($key !== false) {
            $key = md5($this->getCacheKey() . '.' . $layout . json_encode($context));
            $cache = $this->_flexDirectory->getCache('render');
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
            $block = HtmlBlock::create($key);
            $block->setChecksum($checksum);

            $grav->fireEvent('onFlexObjectRender', new Event([
                'object' => $this,
                'layout' => &$layout,
                'context' => &$context
            ]));

            $output = $this->getTemplate($layout)->render(
                ['grav' => $grav, 'block' => $block, 'object' => $this, 'layout' => $layout] + $context
            );

            if ($debugger->enabled()) {
                $name = $this->getKey() . ' (' . $this->getType(false) . ')';
                $output = "\n<!–– START {$name} object ––>\n{$output}\n<!–– END {$name} object ––>\n";
            }

            $block->setContent($output);

            try {
                $cache && $cache->set($key, $block->toArray());
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);
            }
        }

        $debugger->stopTimer('flex-object-' . $debugKey);

        return $block;
    }

    /**
     * Form field compatibility.
     *
     * @param  string $name
     * @param  mixed  $default
     * @param  string $separator
     * @return mixed
     */
    public function value($name, $default = null, $separator = null)
    {
        if ($name === 'storage_key') {
            return $this->getStorageKey();
        }

        return $this->getNestedProperty($name, $default, $separator);
    }

    /**
     * @return bool
     */
    public function exists()
    {
        $key = $this->getStorageKey();

        return $key && $this->getFlexDirectory()->getStorage()->hasKey($key);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getElements();
    }

    /**
     * @return array
     */
    public function prepareStorage()
    {
        return $this->getElements();
    }

    /**
     * @return string
     */
    public function getStorageFolder()
    {
        return $this->getFlexDirectory()->getStorageFolder($this->getStorageKey());
    }

    /**
     * @return string
     */
    public function getMediaFolder()
    {
        return $this->getFlexDirectory()->getMediaFolder($this->getStorageKey());
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
     * Create new object into storage.
     *
     * @param string|null $key Optional new key.
     * @return $this
     */
    public function create($key = null)
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
     * @return $this
     */
    public function save()
    {
        $result = $this->getFlexDirectory()->getStorage()->replaceRows([$this->getStorageKey() => $this->prepareStorage()]);

        try {
            $this->getFlexDirectory()->clearCache();
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        } catch (InvalidArgumentException $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            // Caching failed, but we can ignore that for now.
        }

        $value = reset($result);
        $storageKey = key($result);
        if ($value && $storageKey) {
            $this->setStorageKey($storageKey);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function delete()
    {
        $this->getFlexDirectory()->getStorage()->deleteRows([$this->getStorageKey() => $this->prepareStorage()]);

        try {
            $this->getFlexDirectory()->clearCache();
            if (method_exists($this, 'clearMediaCache')) {
                $this->clearMediaCache();
            }
        } catch (InvalidArgumentException $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            // Caching failed, but we can ignore that for now.
        }

        return $this;
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
            'type:private' => $this->getType(),
            'key:private' => $this->getKey(),
            'elements:private' => $this->getElements(),
            'storage:private' => $this->getStorage()
        ];
    }

    /**
     * @return array
     */
    protected function doSerialize()
    {
        return [
            'type' => $this->getType(false),
            'key' => $this->getKey(),
            'elements' => $this->getElements(),
            'storage' => $this->getStorage()
        ];
    }

    /**
     * @param array $serialized
     */
    protected function doUnserialize(array $serialized)
    {
        $type = $serialized['type'] ?? 'unknown';

        if (!isset($serialized['key'], $serialized['type'], $serialized['elements'])) {
            throw new \InvalidArgumentException("Cannot unserialize '{$type}': Bad data");
        }

        $grav = Grav::instance();
        /** @var Flex $flex */
        $flex = $grav['flex_directory'];
        $directory = $flex->getDirectory($type);
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
    public function setFlexDirectory(FlexDirectory $directory)
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
     * @param string $uri
     * @return Medium|null
     */
    protected function createMedium($uri)
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $file = $uri ? $locator->findResource($uri) : null;

        return $file ? MediumFactory::fromFile($file) : null;
    }

    /**
     * @param string $type
     * @param string $property
     * @return FlexCollection
     */
    protected function getCollectionByProperty($type, $property)
    {
        $directory = $this->getRelatedDirectory($type);
        $collection = $directory->getCollection();
        $list = $this->getNestedProperty($property) ?: [];

        $collection = $collection->filter(function ($object) use ($list) { return \in_array($object->id, $list, true); });

        return $collection;
    }

    /**
     * @param $type
     * @return FlexDirectory
     * @throws \RuntimeException
     */
    protected function getRelatedDirectory($type)
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
     * @return \Twig_Template
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    protected function getTemplate($layout)
    {
        $grav = Grav::instance();

        /** @var Twig $twig */
        $twig = $grav['twig'];

        try {
            return $twig->twig()->resolveTemplate(["flex-objects/layouts/{$this->getType(false)}/object/{$layout}.html.twig"]);
        } catch (\Twig_Error_Loader $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            return $twig->twig()->resolveTemplate(["flex-objects/layouts/404.html.twig"]);
        }
    }

    /**
     * Filter data coming to constructor or $this->update() request.
     *
     * NOTE: The incoming data can be an arbitrary array so do not assume anything from its content.
     *
     * @param array $elements
     */
    protected function filterElements(array &$elements)
    {
        if (!empty($elements['storage_key'])) {
            $this->_storage['storage_key'] = trim($elements['storage_key']);
        }
        if (!empty($elements['storage_timestamp'])) {
            $this->_storage['storage_timestamp'] = (int)$elements['storage_timestamp'];
        }

        unset ($elements['storage_key'], $elements['storage_timestamp']);
    }

    /**
     * This methods allows you to override form objects in child classes.
     *
     * @param string $name Form name
     * @return FlexFormInterface
     */
    protected function createFormObject(string $name): FlexFormInterface
    {
        return new FlexForm($name, $this);
    }
}
