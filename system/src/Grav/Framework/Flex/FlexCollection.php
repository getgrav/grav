<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Twig\Twig;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Cache\CacheInterface;
use Grav\Framework\ContentBlock\ContentBlockInterface;
use Grav\Framework\ContentBlock\HtmlBlock;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\ObjectCollection;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RocketTheme\Toolbox\Event\Event;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\TemplateWrapper;

/**
 * Class FlexCollection
 * @package Grav\Framework\Flex
 */
class FlexCollection extends ObjectCollection implements FlexCollectionInterface
{
    /** @var FlexDirectory */
    private $_flexDirectory;

    /** @var string */
    private $_keyField;

    /**
     * Get list of cached methods.
     *
     * @return array Returns a list of methods with their caching information.
     */
    public static function getCachedMethods(): array
    {
        return [
            'getTypePrefix' => true,
            'getType' => true,
            'getFlexDirectory' => true,
            'getCacheKey' => true,
            'getCacheChecksum' => true,
            'getTimestamp' => true,
            'hasProperty' => true,
            'getProperty' => true,
            'hasNestedProperty' => true,
            'getNestedProperty' => true,
            'orderBy' => true,

            'render' => false,
            'isAuthorized' => 'session',
            'search' => true,
            'sort' => true,
        ];
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
     * {@inheritdoc}
     * @see FlexCollectionInterface::__construct()
     */
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        parent::__construct($entries);

        if ($directory) {
            $this->setFlexDirectory($directory)->setKey($directory->getFlexType());
        }
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::search()
     */
    public function search(string $search, $properties = null, array $options = null)
    {
        $matching = $this->call('search', [$search, $properties, $options]);
        $matching = array_filter($matching);

        if ($matching) {
            uksort($matching, function ($a, $b) {
                return -($a <=> $b);
            });
        }

        return $this->select(array_keys($matching));
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::sort()
     */
    public function sort(array $order)
    {
        $criteria = Criteria::create()->orderBy($order);

        /** @var FlexCollectionInterface $matching */
        $matching = $this->matching($criteria);

        return $matching;
    }

    /**
     * @param array $filters
     * @return FlexCollectionInterface|Collection
     */
    public function filterBy(array $filters)
    {
        $expr = Criteria::expr();
        $criteria = Criteria::create();

        foreach ($filters as $key => $value) {
            $criteria->andWhere($expr->eq($key, $value));
        }

        return $this->matching($criteria);
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
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getCacheKey(): string
    {
        return $this->getTypePrefix() . $this->getFlexType() . '.' . sha1(json_encode($this->call('getKey')));
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getCacheChecksum(): string
    {
        return sha1(json_encode($this->getTimestamps()));
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getTimestamps(): array
    {
        /** @var int[] $timestamps */
        $timestamps = $this->call('getTimestamp');

        return $timestamps;
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getStorageKeys(): array
    {
        /** @var string[] $keys */
        $keys = $this->call('getStorageKey');

        return $keys;
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::getFlexDirectory()
     */
    public function getFlexKeys(): array
    {
        /** @var string[] $keys */
        $keys = $this->call('getFlexKey');

        return $keys;
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::withKeyField()
     */
    public function withKeyField(string $keyField = null)
    {
        $keyField = $keyField ?: 'key';
        if ($keyField === $this->getKeyField()) {
            return $this;
        }

        $entries = [];
        foreach ($this as $key => $object) {
            // TODO: remove hardcoded logic
            if ($keyField === 'storage_key') {
                $entries[$object->getStorageKey()] = $object;
            } elseif ($keyField === 'flex_key') {
                $entries[$object->getFlexKey()] = $object;
            } elseif ($keyField === 'key') {
                $entries[$object->getKey()] = $object;
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
        return $this->getFlexDirectory()->getIndex($this->getKeys(), $this->getKeyField());
    }

    /**
     * {@inheritdoc}
     * @see FlexCollectionInterface::render()
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
        $debugger->startTimer('flex-collection-' . ($debugKey =  uniqid($type, false)), 'Render Collection ' . $type . ' (' . $layout . ')');

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
            if ($key === false) {
                $block->disableCache();
            }

            $grav->fireEvent('onFlexCollectionRender', new Event([
                'collection' => $this,
                'layout' => &$layout,
                'context' => &$context
            ]));

            $output = $this->getTemplate($layout)->render(
                ['grav' => $grav, 'config' => $grav['config'], 'block' => $block, 'collection' => $this, 'layout' => $layout] + $context
            );

            if ($debugger->enabled()) {
                $output = "\n<!–– START {$type} collection ––>\n{$output}\n<!–– END {$type} collection ––>\n";
            }

            $block->setContent($output);

            try {
                $cache && $block->isCached() && $cache->set($key, $block->toArray());
            } catch (InvalidArgumentException $e) {
                $debugger->addException($e);
            }
        }

        $debugger->stopTimer('flex-collection-' . $debugKey);

        return $block;
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
     * @param FlexDirectory $type
     * @return $this
     */
    public function setFlexDirectory(FlexDirectory $type)
    {
        $this->_flexDirectory = $type;

        return $this;
    }

    /**
     * @return array
     */
    public function getMetaData(string $key) : array
    {
        $object = $this->get($key);

        return $object instanceof FlexObjectInterface ? $object->getMetaData() : [];
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
     * @return string
     */
    public function getKeyField(): string
    {
        return $this->_keyField ?? 'storage_key';
    }

    /**
     * @param string $action
     * @param string|null $scope
     * @param UserInterface|null $user
     * @return static
     */
    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null)
    {
        $list = $this->call('isAuthorized', [$action, $scope, $user]);
        $list = \array_filter($list);

        return $this->select(array_keys($list));
    }

    /**
     * @param string $value
     * @param string $field
     * @return FlexObject|null
     */
    public function find($value, $field = 'id')
    {
        if ($value) foreach ($this as $element) {
            if (mb_strtolower($element->getProperty($field)) === mb_strtolower($value)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $elements = [];

        /**
         * @var string $key
         * @var array|FlexObject $object
         */
        foreach ($this->getElements() as $key => $object) {
            $elements[$key] = \is_array($object) ? $object : $object->jsonSerialize();
        }

        return $elements;
    }

    public function __debugInfo()
    {
        return [
            'type:private' => $this->getFlexType(),
            'key:private' => $this->getKey(),
            'objects_key:private' => $this->getKeyField(),
            'objects:private' => $this->getElements()
        ];
    }

    /**
     * Creates a new instance from the specified elements.
     *
     * This method is provided for derived classes to specify how a new
     * instance should be created when constructor semantics have changed.
     *
     * @param array $elements Elements.
     * @param string|null $keyField
     *
     * @return static
     * @throws \InvalidArgumentException
     */
    protected function createFrom(array $elements, $keyField = null)
    {
        $collection = new static($elements, $this->_flexDirectory);
        $collection->setKeyField($keyField ?: $this->_keyField);

        return $collection;
    }

    /**
     * @return string
     */
    protected function getTypePrefix(): string
    {
        return 'c.';
    }

    /**
     * @param string $layout
     * @return TemplateWrapper
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
                    "flex-objects/layouts/{$this->getFlexType()}/collection/{$layout}.html.twig",
                    "flex-objects/layouts/_default/collection/{$layout}.html.twig"
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
     * @param string $type
     * @return FlexDirectory
     */
    protected function getRelatedDirectory($type): ?FlexDirectory
    {
        /** @var Flex $flex */
        $flex = Grav::instance()['flex_objects'];

        return $flex->getDirectory($type);
    }

    protected function setKeyField($keyField = null): void
    {
        $this->_keyField = $keyField ?? 'storage_key';
    }
}
