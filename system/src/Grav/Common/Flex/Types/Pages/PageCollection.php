<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages;

use Exception;
use Grav\Common\Flex\Traits\FlexCollectionTrait;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Grav;
use Grav\Common\Page\Header;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Utils;
use Grav\Framework\Flex\Pages\FlexPageCollection;
use Collator;
use InvalidArgumentException;
use RuntimeException;
use function array_search;
use function count;
use function extension_loaded;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class GravPageCollection
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * @extends FlexPageCollection<PageObject>
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
 *
 * @method static shuffle()
 * @method static select(array $keys)
 * @method static unselect(array $keys)
 * @method static createFrom(array $elements, string $keyField = null)
 * @method PageIndex getIndex()
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
                'pages' => true,
                'modules' => true,
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
     * @return PageObject
     */
    public function getRoot()
    {
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
        if (!$page instanceof PageObject) {
            throw new InvalidArgumentException('$page is not a flex page.');
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
        throw new RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Intersect another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return static
     */
    public function intersect(PageCollectionInterface $collection)
    {
        throw new RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Set current page.
     */
    public function setCurrent(string $path): void
    {
        throw new RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Return previous item.
     *
     * @return PageInterface|false
     * @phpstan-return PageObject|false
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
     * @phpstan-return PageObject|false
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
        throw new RuntimeException(__METHOD__ . '(): Not Implemented');
    }

    /**
     * Split collection into array of smaller collections.
     *
     * @param int $size
     * @return static[]
     */
    public function batch($size): array
    {
        $chunks = $this->chunk($size);

        $list = [];
        foreach ($chunks as $chunk) {
            $list[] = $this->createFrom($chunk);
        }

        return $list;
    }

    /**
     * Reorder collection.
     *
     * @param string $by
     * @param string $dir
     * @param array|null  $manual
     * @param int|null $sort_flags
     * @return static
     */
    public function order($by, $dir = 'asc', $manual = null, $sort_flags = null)
    {
        if (!$this->count()) {
            return $this;
        }

        if ($by === 'random') {
            return $this->shuffle();
        }

        $keys = $this->buildSort($by, $dir, $manual, $sort_flags);

        return $this->createFrom(array_replace(array_flip($keys), $this->toArray()) ?? []);
    }

    /**
     * @param string $order_by
     * @param string $order_dir
     * @param array|null  $manual
     * @param int|null    $sort_flags
     * @return array
     */
    protected function buildSort($order_by = 'default', $order_dir = 'asc', $manual = null, $sort_flags = null): array
    {
        // do this header query work only once
        $header_query = null;
        $header_default = null;
        if (strpos($order_by, 'header.') === 0) {
            $query = explode('|', str_replace('header.', '', $order_by), 2);
            $header_query = array_shift($query) ?? '';
            $header_default = array_shift($query);
        }

        $list = [];
        foreach ($this as $key => $child) {
            switch ($order_by) {
                case 'title':
                    $list[$key] = $child->title();
                    break;
                case 'date':
                    $list[$key] = $child->date();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'modified':
                    $list[$key] = $child->modified();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'publish_date':
                    $list[$key] = $child->publishDate();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'unpublish_date':
                    $list[$key] = $child->unpublishDate();
                    $sort_flags = SORT_REGULAR;
                    break;
                case 'slug':
                    $list[$key] = $child->slug();
                    break;
                case 'basename':
                    $list[$key] = basename($key);
                    break;
                case 'folder':
                    $list[$key] = $child->folder();
                    break;
                case 'manual':
                case 'default':
                default:
                    if (is_string($header_query)) {
                        /** @var Header $child_header */
                        $child_header = $child->header();
                        $header_value = $child_header->get($header_query);
                        if (is_array($header_value)) {
                            $list[$key] = implode(',', $header_value);
                        } elseif ($header_value) {
                            $list[$key] = $header_value;
                        } else {
                            $list[$key] = $header_default ?: $key;
                        }
                        $sort_flags = $sort_flags ?: SORT_REGULAR;
                        break;
                    }
                    $list[$key] = $key;
                    $sort_flags = $sort_flags ?: SORT_REGULAR;
            }
        }

        if (null === $sort_flags) {
            $sort_flags = SORT_NATURAL | SORT_FLAG_CASE;
        }

        // else just sort the list according to specified key
        if (extension_loaded('intl') && Grav::instance()['config']->get('system.intl_enabled')) {
            $locale = setlocale(LC_COLLATE, '0'); //`setlocale` with a '0' param returns the current locale set
            $col = Collator::create($locale);
            if ($col) {
                $col->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
                if (($sort_flags & SORT_NATURAL) === SORT_NATURAL) {
                    $list = preg_replace_callback('~([0-9]+)\.~', static function ($number) {
                        return sprintf('%032d.', $number[0]);
                    }, $list);
                    if (!is_array($list)) {
                        throw new RuntimeException('Internal Error');
                    }

                    $list_vals = array_values($list);
                    if (is_numeric(array_shift($list_vals))) {
                        $sort_flags = Collator::SORT_REGULAR;
                    } else {
                        $sort_flags = Collator::SORT_STRING;
                    }
                }

                $col->asort($list, $sort_flags);
            } else {
                asort($list, $sort_flags);
            }
        } else {
            asort($list, $sort_flags);
        }

        // Move manually ordered items into the beginning of the list. Order of the unlisted items does not change.
        if (is_array($manual) && !empty($manual)) {
            $i = count($manual);
            $new_list = [];
            foreach ($list as $key => $dummy) {
                $child = $this[$key] ?? null;
                $order = $child ? array_search($child->slug, $manual, true) : false;
                if ($order === false) {
                    $order = $i++;
                }
                $new_list[$key] = (int)$order;
            }

            $list = $new_list;

            // Apply manual ordering to the list.
            asort($list, SORT_NUMERIC);
        }

        if ($order_dir !== 'asc') {
            $list = array_reverse($list);
        }

        return array_keys($list);
    }

    /**
     * Mimicks Pages class.
     *
     * @return $this
     * @deprecated 1.7 Not needed anymore in Flex Pages (does nothing).
     */
    public function all()
    {
        return $this;
    }

    /**
     * Returns the items between a set of date ranges of either the page date field (default) or
     * an arbitrary datetime page field where start date and end date are optional
     * Dates must be passed in as text that strtotime() can process
     * http://php.net/manual/en/function.strtotime.php
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $field
     * @return static
     * @throws Exception
     */
    public function dateRange($startDate = null, $endDate = null, $field = null)
    {
        $start = $startDate ? Utils::date2timestamp($startDate) : null;
        $end = $endDate ? Utils::date2timestamp($endDate) : null;

        $entries = [];
        foreach ($this as $key => $object) {
            if (!$object) {
                continue;
            }

            $date = $field ? strtotime($object->getNestedProperty($field)) : $object->date();

            if ((!$start || $date >= $start) && (!$end || $date <= $end)) {
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
     * Creates new collection with only pages
     *
     * @return static The collection with only pages
     */
    public function pages()
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
     * Creates new collection with only modules
     *
     * @return static The collection with only modules
     */
    public function modules()
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
     * Alias of modules()
     *
     * @return static
     */
    public function modular()
    {
        return $this->modules();
    }

    /**
     * Alias of pages()
     *
     * @return static
     */
    public function nonModular()
    {
        return $this->pages();
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
            if ($object && in_array($object->template(), $types, true)) {
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
                if (is_array($object->header()->access)) {
                    //Multiple values for access
                    $valid = false;

                    foreach ($object->header()->access as $index => $accessLevel) {
                        if (is_array($accessLevel)) {
                            foreach ($accessLevel as $innerIndex => $innerAccessLevel) {
                                if (in_array($innerAccessLevel, $accessLevels)) {
                                    $valid = true;
                                }
                            }
                        } else {
                            if (in_array($index, $accessLevels)) {
                                $valid = true;
                            }
                        }
                    }
                    if ($valid) {
                        $entries[$key] = $object;
                    }
                } else {
                    //Single value for access
                    if (in_array($object->header()->access, $accessLevels)) {
                        $entries[$key] = $object;
                    }
                }
            }
        }

        return $this->createFrom($entries);
    }

    /**
     * @param bool $bool
     * @return static
     */
    public function withOrdered(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isOrdered', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @return static
     */
    public function withModules(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isModule', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @return static
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
     * @return static
     */
    public function withTranslation(bool $bool = true, string $languageCode = null, bool $fallback = null)
    {
        $list = array_keys(array_filter($this->call('hasTranslation', [$languageCode, $fallback])));

        return $bool ? $this->select($list) : $this->unselect($list);
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return PageIndex
     */
    public function withTranslated(string $languageCode = null, bool $fallback = null)
    {
        return $this->getIndex()->withTranslated($languageCode, $fallback);
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
     * @return static
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
     * @throws Exception
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
