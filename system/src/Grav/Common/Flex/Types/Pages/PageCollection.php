<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages;

use Grav\Common\Flex\Traits\FlexCollectionTrait;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Pages\FlexPageCollection;

/**
 * Class GravPageCollection
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * Incompatibilities with Grav\Common\Page\Collection:
 *     $page = $collection->key()       will not work at all
 *     $clone = clone $collection       does not clone objects inside the collection, does it matter?
 *     $string = (string)$collection    returns collection id instead of comma separated list
 *     $collection->add()               incompatible method signature
 *     $collection->remove()            incompatible method signature
 *     $collection->filter()            incompatible method signature (takes closure instead of callable)
 *     $collection->prev()              does not rewind the internal pointer
 * AND most methods are immutable; they do not update the current collection, but return updated one
 */
class PageCollection extends FlexPageCollection implements PageCollectionInterface
{
    use FlexGravTrait;
    use FlexCollectionTrait;

    /** @var array|null */
    protected $_params;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
                // Collection specific methods
                'getRoot' => false,
                'getParams' => false,
                'setParams' => false,
                'params' => false,
                'addPage' => false,
                'merge' => false,
                'intersect' => false,
                'prev' => false,
                'nth' => false,
                'random' => false,
                'append' => false,
                'batch' => false,
                'order' => false,

                // Collection filtering
                'dateRange' => true,
                'visible' => true,
                'nonVisible' => true,
                'modular' => true,
                'nonModular' => true,
                'published' => true,
                'nonPublished' => true,
                'routable' => true,
                'nonRoutable' => true,
                'ofType' => true,
                'ofOneOfTheseTypes' => true,
                'ofOneOfTheseAccessLevels' => true,
                'withOrdered' => true,
                'withModules' => true,
                'withPages' => true,
                'withTranslation' => true,
                'filterBy' => true,

                'toExtendedArray' => false,
                'getLevelListing' => false,
            ] + parent::getCachedMethods();
    }

    /**
     * @return PageInterface|FlexObjectInterface
     */
    public function getRoot()
    {
        /** @var PageIndex $index */
        $index = $this->getIndex();

        return $index->getRoot();
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->_params ?? [];
    }

    /**
     * Set parameters to the Collection
     *
     * @param array $params
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->_params = $this->_params ? array_merge($this->_params, $params) : $params;

        return $this;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function params(): array
    {
        return $this->getParams();
    }

    /**
     * Add a single page to a collection
     *
     * @param PageInterface $page
     * @return static
     */
    public function addPage(PageInterface $page)
    {
        if (!$page instanceof FlexObjectInterface) {
            throw new \InvalidArgumentException('$page is not a flex page.');
        }

        // FIXME: support other keys.
        $this->set($page->getKey(), $page);

        return $this;
    }

    /**
     *
     * Merge another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return static
     */
    public function merge(PageCollectionInterface $collection)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Intersect another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return static
     */
    public function intersect(PageCollectionInterface $collection)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Return previous item.
     *
     * @return PageInterface|false
     */
    public function prev()
    {
        // FIXME: this method does not rewind the internal pointer!
        $key = (string)$this->key();
        $prev = $this->prevSibling($key);

        return $prev !== $this->current() ? $prev : false;
    }

    /**
     * Return nth item.
     * @param int $key
     * @return PageInterface|bool
     */
    public function nth($key)
    {
        return $this->slice($key, 1)[0] ?? false;
    }

    /**
     * Pick one or more random entries.
     *
     * @param int $num Specifies how many entries should be picked.
     * @return static
     */
    public function random($num = 1)
    {
        return $this->createFrom($this->shuffle()->slice(0, $num));
    }

    /**
     * Append new elements to the list.
     *
     * @param array $items Items to be appended. Existing keys will be overridden with the new values.
     * @return static
     */
    public function append($items)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Split collection into array of smaller collections.
     *
     * @param int $size
     * @return static[]
     */
    public function batch($size): array
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Reorder collection.
     *
     * @param string $by
     * @param string $dir
     * @param array  $manual
     * @param string $sort_flags
     * @return static
     */
    public function order($by, $dir = 'asc', $manual = null, $sort_flags = null)
    {
        throw new \RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Returns the items between a set of date ranges of either the page date field (default) or
     * an arbitrary datetime page field where end date is optional
     * Dates can be passed in as text that strtotime() can process
     * http://php.net/manual/en/function.strtotime.php
     *
     * @param string $startDate
     * @param string|false $endDate
     * @param string|null $field
     * @return static
     * @throws \Exception
     */
    public function dateRange($startDate, $endDate = false, $field = null)
    {
        $start = Utils::date2timestamp($startDate);
        $end = $endDate ? Utils::date2timestamp($endDate) : false;

        $entries = [];
        foreach ($this as $key => $object) {
            if (!$object) {
                continue;
            }

            $date = $field ? strtotime($object->getNestedProperty($field)) : $object->date();

            if ($date >= $start && (!$end || $date <= $end)) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only visible pages
     *
     * @return static The collection with only visible pages
     */
    public function visible()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->visible()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-visible pages
     *
     * @return static The collection with only non-visible pages
     */
    public function nonVisible()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->visible()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only modular pages
     *
     * @return static The collection with only modular pages
     */
    public function modular()
    {
        $entries = [];
        /**
         * @var int|string $key
         * @var PageInterface|null $object
         */
        foreach ($this as $key => $object) {
            if ($object && $object->isModule()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-modular pages
     *
     * @return static The collection with only non-modular pages
     */
    public function nonModular()
    {
        $entries = [];
        /**
         * @var int|string $key
         * @var PageInterface|null $object
         */
        foreach ($this as $key => $object) {
            if ($object && !$object->isModule()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only published pages
     *
     * @return static The collection with only published pages
     */
    public function published()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->published()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-published pages
     *
     * @return static The collection with only non-published pages
     */
    public function nonPublished()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->published()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only routable pages
     *
     * @return static The collection with only routable pages
     */
    public function routable()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->routable()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only non-routable pages
     *
     * @return static The collection with only non-routable pages
     */
    public function nonRoutable()
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && !$object->routable()) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only pages of the specified type
     *
     * @param string $type
     * @return static The collection
     */
    public function ofType($type)
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && $object->template() === $type) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only pages of one of the specified types
     *
     * @param string[] $types
     * @return static The collection
     */
    public function ofOneOfTheseTypes($types)
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && \in_array($object->template(), $types, true)) {
                $entries[$key] = $object;
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * Creates new collection with only pages of one of the specified access levels
     *
     * @param array $accessLevels
     * @return static The collection
     */
    public function ofOneOfTheseAccessLevels($accessLevels)
    {
        $entries = [];
        foreach ($this as $key => $object) {
            if ($object && isset($object->header()->access)) {
                if (\is_array($object->header()->access)) {
                    //Multiple values for access
                    $valid = false;

                    foreach ($object->header()->access as $index => $accessLevel) {
                        if (\is_array($accessLevel)) {
                            foreach ($accessLevel as $innerIndex => $innerAccessLevel) {
                                if (\in_array($innerAccessLevel, $accessLevels)) {
                                    $valid = true;
                                }
                            }
                        } else {
                            if (\in_array($index, $accessLevels)) {
                                $valid = true;
                            }
                        }
                    }
                    if ($valid) {
                        $entries[$key] = $object;
                    }
                } else {
                    //Single value for access
                    if (\in_array($object->header()->access, $accessLevels)) {
                        $entries[$key] = $object;
                    }
                }
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * @param bool $bool
     * @return FlexCollectionInterface|FlexPageCollection
     */
    public function withOrdered(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isOrdered', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @return FlexCollectionInterface|FlexPageCollection
     */
    public function withModules(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isModule', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @return FlexCollectionInterface|FlexPageCollection
     */
    public function withPages(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isPage', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return FlexCollectionInterface|FlexPageCollection
     */
    public function withTranslation(bool $bool = true, string $languageCode = null, bool $fallback = null)
    {
        $list = array_keys(array_filter($this->call('hasTranslation', [$languageCode, $fallback])));

        return $bool ? $this->select($list) : $this->unselect($list);
    }

    /**
     * Filter pages by given filters.
     *
     * - search: string
     * - page_type: string|string[]
     * - modular: bool
     * - visible: bool
     * - routable: bool
     * - published: bool
     * - page: bool
     * - translated: bool
     *
     * @param array $filters
     * @param bool $recursive
     * @return FlexCollectionInterface
     */
    public function filterBy(array $filters, bool $recursive = false)
    {
        $list = array_keys(array_filter($this->call('filterBy', [$filters, $recursive])));

        return $this->select($list);
    }

    /**
     * Get the extended version of this Collection with each page keyed by route
     *
     * @return array
     * @throws \Exception
     */
    public function toExtendedArray(): array
    {
        $entries  = [];
        foreach ($this as $key => $object) {
            if ($object) {
                $entries[$object->route()] = $object->toArray();
            }
        }
        return $entries;
    }

    /**
     * @param array $options
     * @return array
     */
    public function getLevelListing(array $options): array
    {
        /** @var PageIndex $index */
        $index = $this->getIndex();

        return method_exists($index, 'getLevelListing') ? $index->getLevelListing($options) : [];
    }
}
