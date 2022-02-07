<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Iterator;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Utils;
use InvalidArgumentException;
use function array_key_exists;
use function array_keys;
use function array_search;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class Collection
 * @package Grav\Common\Page
 * @implements PageCollectionInterface<string,Page>
 */
class Collection extends Iterator implements PageCollectionInterface
{
    /** @var Pages */
    protected $pages;
    /** @var array */
    protected $params;

    /**
     * Collection constructor.
     *
     * @param array      $items
     * @param array      $params
     * @param Pages|null $pages
     */
    public function __construct($items = [], array $params = [], Pages $pages = null)
    {
        parent::__construct($items);

        $this->params = $params;
        $this->pages = $pages ?: Grav::instance()->offsetGet('pages');
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function params()
    {
        return $this->params;
    }

    /**
     * Set parameters to the Collection
     *
     * @param array $params
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    /**
     * Add a single page to a collection
     *
     * @param PageInterface $page
     * @return $this
     */
    public function addPage(PageInterface $page)
    {
        $this->items[$page->path()] = ['slug' => $page->slug()];

        return $this;
    }

    /**
     * Add a page with path and slug
     *
     * @param string $path
     * @param string $slug
     * @return $this
     */
    public function add($path, $slug)
    {
        $this->items[$path] = ['slug' => $slug];

        return $this;
    }

    /**
     *
     * Create a copy of this collection
     *
     * @return static
     */
    public function copy()
    {
        return new static($this->items, $this->params, $this->pages);
    }

    /**
     *
     * Merge another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return $this
     */
    public function merge(PageCollectionInterface $collection)
    {
        foreach ($collection as $page) {
            $this->addPage($page);
        }

        return $this;
    }

    /**
     * Intersect another collection with the current collection
     *
     * @param PageCollectionInterface $collection
     * @return $this
     */
    public function intersect(PageCollectionInterface $collection)
    {
        $array1 = $this->items;
        $array2 = $collection->toArray();

        $this->items = array_uintersect($array1, $array2, function ($val1, $val2) {
            return strcmp($val1['slug'], $val2['slug']);
        });

        return $this;
    }

    /**
     * Set current page.
     */
    public function setCurrent(string $path): void
    {
        reset($this->items);

        while (($key = key($this->items)) !== null && $key !== $path) {
            next($this->items);
        }
    }

    /**
     * Returns current page.
     *
     * @return PageInterface
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        $current = parent::key();

        return $this->pages->get($current);
    }

    /**
     * Returns current slug.
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        $current = parent::current();

        return $current['slug'];
    }

    /**
     * Returns the value at specified offset.
     *
     * @param string $offset
     * @return PageInterface|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->pages->get($offset) ?: null;
    }

    /**
     * Split collection into array of smaller collections.
     *
     * @param int $size
     * @return Collection[]
     */
    public function batch($size)
    {
        $chunks = array_chunk($this->items, $size, true);

        $list = [];
        foreach ($chunks as $chunk) {
            $list[] = new static($chunk, $this->params, $this->pages);
        }

        return $list;
    }

    /**
     * Remove item from the list.
     *
     * @param PageInterface|string|null $key
     * @return $this
     * @throws InvalidArgumentException
     */
    public function remove($key = null)
    {
        if ($key instanceof PageInterface) {
            $key = $key->path();
        } elseif (null === $key) {
            $key = (string)key($this->items);
        }
        if (!is_string($key)) {
            throw new InvalidArgumentException('Invalid argument $key.');
        }

        parent::remove($key);

        return $this;
    }

    /**
     * Reorder collection.
     *
     * @param string $by
     * @param string $dir
     * @param array|null  $manual
     * @param string|null $sort_flags
     * @return $this
     */
    public function order($by, $dir = 'asc', $manual = null, $sort_flags = null)
    {
        $this->items = $this->pages->sortCollection($this, $by, $dir, $manual, $sort_flags);

        return $this;
    }

    /**
     * Check to see if this item is the first in the collection.
     *
     * @param  string $path
     * @return bool True if item is first.
     */
    public function isFirst($path): bool
    {
        return $this->items && $path === array_keys($this->items)[0];
    }

    /**
     * Check to see if this item is the last in the collection.
     *
     * @param  string $path
     * @return bool True if item is last.
     */
    public function isLast($path): bool
    {
        return $this->items && $path === array_keys($this->items)[count($this->items) - 1];
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @param  string $path
     *
     * @return PageInterface  The previous item.
     */
    public function prevSibling($path)
    {
        return $this->adjacentSibling($path, -1);
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @param  string $path
     *
     * @return PageInterface The next item.
     */
    public function nextSibling($path)
    {
        return $this->adjacentSibling($path, 1);
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  string  $path
     * @param  int $direction either -1 or +1
     * @return PageInterface|Collection    The sibling item.
     */
    public function adjacentSibling($path, $direction = 1)
    {
        $values = array_keys($this->items);
        $keys = array_flip($values);

        if (array_key_exists($path, $keys)) {
            $index = $keys[$path] - $direction;

            return isset($values[$index]) ? $this->offsetGet($values[$index]) : $this;
        }

        return $this;
    }

    /**
     * Returns the item in the current position.
     *
     * @param  string $path the path the item
     * @return int|null The index of the current page, null if not found.
     */
    public function currentPosition($path): ?int
    {
        $pos = array_search($path, array_keys($this->items), true);

        return $pos !== false ? $pos : null;
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
     * @return $this
     * @throws Exception
     */
    public function dateRange($startDate = null, $endDate = null, $field = null)
    {
        $start = $startDate ? Utils::date2timestamp($startDate) : null;
        $end = $endDate ? Utils::date2timestamp($endDate) : null;

        $date_range = [];
        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if (!$page) {
                continue;
            }

            $date = $field ? strtotime($page->value($field)) : $page->date();

            if ((!$start || $date >= $start) && (!$end || $date <= $end)) {
                $date_range[$path] = $slug;
            }
        }

        $this->items = $date_range;

        return $this;
    }

    /**
     * Creates new collection with only visible pages
     *
     * @return Collection The collection with only visible pages
     */
    public function visible()
    {
        $visible = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->visible()) {
                $visible[$path] = $slug;
            }
        }
        $this->items = $visible;

        return $this;
    }

    /**
     * Creates new collection with only non-visible pages
     *
     * @return Collection The collection with only non-visible pages
     */
    public function nonVisible()
    {
        $visible = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && !$page->visible()) {
                $visible[$path] = $slug;
            }
        }
        $this->items = $visible;

        return $this;
    }

    /**
     * Creates new collection with only pages
     *
     * @return Collection The collection with only pages
     */
    public function pages()
    {
        $modular = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && !$page->isModule()) {
                $modular[$path] = $slug;
            }
        }
        $this->items = $modular;

        return $this;
    }

    /**
     * Creates new collection with only modules
     *
     * @return Collection The collection with only modules
     */
    public function modules()
    {
        $modular = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->isModule()) {
                $modular[$path] = $slug;
            }
        }
        $this->items = $modular;

        return $this;
    }

    /**
     * Alias of pages()
     *
     * @return Collection The collection with only non-module pages
     */
    public function nonModular()
    {
        $this->pages();

        return $this;
    }

    /**
     * Alias of modules()
     *
     * @return Collection The collection with only modules
     */
    public function modular()
    {
        $this->modules();

        return $this;
    }

    /**
     * Creates new collection with only translated pages
     *
     * @return Collection The collection with only published pages
     * @internal
     */
    public function translated()
    {
        $published = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->translated()) {
                $published[$path] = $slug;
            }
        }
        $this->items = $published;

        return $this;
    }

    /**
     * Creates new collection with only untranslated pages
     *
     * @return Collection The collection with only non-published pages
     * @internal
     */
    public function nonTranslated()
    {
        $published = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && !$page->translated()) {
                $published[$path] = $slug;
            }
        }
        $this->items = $published;

        return $this;
    }

    /**
     * Creates new collection with only published pages
     *
     * @return Collection The collection with only published pages
     */
    public function published()
    {
        $published = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->published()) {
                $published[$path] = $slug;
            }
        }
        $this->items = $published;

        return $this;
    }

    /**
     * Creates new collection with only non-published pages
     *
     * @return Collection The collection with only non-published pages
     */
    public function nonPublished()
    {
        $published = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && !$page->published()) {
                $published[$path] = $slug;
            }
        }
        $this->items = $published;

        return $this;
    }

    /**
     * Creates new collection with only routable pages
     *
     * @return Collection The collection with only routable pages
     */
    public function routable()
    {
        $routable = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);

            if ($page !== null && $page->routable()) {
                $routable[$path] = $slug;
            }
        }

        $this->items = $routable;

        return $this;
    }

    /**
     * Creates new collection with only non-routable pages
     *
     * @return Collection The collection with only non-routable pages
     */
    public function nonRoutable()
    {
        $routable = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && !$page->routable()) {
                $routable[$path] = $slug;
            }
        }
        $this->items = $routable;

        return $this;
    }

    /**
     * Creates new collection with only pages of the specified type
     *
     * @param string $type
     * @return Collection The collection
     */
    public function ofType($type)
    {
        $items = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->template() === $type) {
                $items[$path] = $slug;
            }
        }

        $this->items = $items;

        return $this;
    }

    /**
     * Creates new collection with only pages of one of the specified types
     *
     * @param string[] $types
     * @return Collection The collection
     */
    public function ofOneOfTheseTypes($types)
    {
        $items = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && in_array($page->template(), $types, true)) {
                $items[$path] = $slug;
            }
        }

        $this->items = $items;

        return $this;
    }

    /**
     * Creates new collection with only pages of one of the specified access levels
     *
     * @param array $accessLevels
     * @return Collection The collection
     */
    public function ofOneOfTheseAccessLevels($accessLevels)
    {
        $items = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);

            if ($page !== null && isset($page->header()->access)) {
                if (is_array($page->header()->access)) {
                    //Multiple values for access
                    $valid = false;

                    foreach ($page->header()->access as $index => $accessLevel) {
                        if (is_array($accessLevel)) {
                            foreach ($accessLevel as $innerIndex => $innerAccessLevel) {
                                if (in_array($innerAccessLevel, $accessLevels, false)) {
                                    $valid = true;
                                }
                            }
                        } else {
                            if (in_array($index, $accessLevels, false)) {
                                $valid = true;
                            }
                        }
                    }
                    if ($valid) {
                        $items[$path] = $slug;
                    }
                } else {
                    //Single value for access
                    if (in_array($page->header()->access, $accessLevels, false)) {
                        $items[$path] = $slug;
                    }
                }
            }
        }

        $this->items = $items;

        return $this;
    }

    /**
     * Get the extended version of this Collection with each page keyed by route
     *
     * @return array
     * @throws Exception
     */
    public function toExtendedArray()
    {
        $items  = [];
        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);

            if ($page !== null) {
                $items[$page->route()] = $page->toArray();
            }
        }
        return $items;
    }
}
