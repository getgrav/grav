<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Grav;
use Grav\Common\Iterator;
use Grav\Common\Utils;

class Collection extends Iterator
{
    /**
     * @var Pages
     */
    protected $pages;

    /**
     * @var array
     */
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
        $this->pages = $pages ? $pages : Grav::instance()->offsetGet('pages');
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
     * Add a single page to a collection
     *
     * @param Page $page
     *
     * @return $this
     */
    public function addPage(Page $page)
    {
        $this->items[$page->path()] = ['slug' => $page->slug()];

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
     * Set parameters to the Collection
     *
     * @param array $params
     *
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    /**
     * Returns current page.
     *
     * @return Page
     */
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
    public function key()
    {
        $current = parent::current();

        return $current['slug'];
    }

    /**
     * Returns the value at specified offset.
     *
     * @param mixed $offset The offset to retrieve.
     *
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        return !empty($this->items[$offset]) ? $this->pages->get($offset) : null;
    }

    /**
     * Remove item from the list.
     *
     * @param Page|string|null $key
     *
     * @return $this|void
     * @throws \InvalidArgumentException
     */
    public function remove($key = null)
    {
        if ($key instanceof Page) {
            $key = $key->path();
        } elseif (is_null($key)) {
            $key = key($this->items);
        }
        if (!is_string($key)) {
            throw new \InvalidArgumentException('Invalid argument $key.');
        }

        parent::remove($key);

        return $this;
    }

    /**
     * Reorder collection.
     *
     * @param string $by
     * @param string $dir
     * @param array  $manual
     *
     * @return $this
     */
    public function order($by, $dir = 'asc', $manual = null)
    {
        $this->items = $this->pages->sortCollection($this, $by, $dir, $manual);

        return $this;
    }

    /**
     * Check to see if this item is the first in the collection.
     *
     * @param  string $path
     *
     * @return boolean True if item is first.
     */
    public function isFirst($path)
    {
        if ($this->items && $path == array_keys($this->items)[0]) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check to see if this item is the last in the collection.
     *
     * @param  string $path
     *
     * @return boolean True if item is last.
     */
    public function isLast($path)
    {
        if ($this->items && $path == array_keys($this->items)[count($this->items) - 1]) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @param  string $path
     *
     * @return Page  The previous item.
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
     * @return Page The next item.
     */
    public function nextSibling($path)
    {
        return $this->adjacentSibling($path, 1);
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  string  $path
     * @param  integer $direction either -1 or +1
     *
     * @return Page    The sibling item.
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
     *
     * @return Integer   the index of the current page.
     */
    public function currentPosition($path)
    {
        return array_search($path, array_keys($this->items));
    }

    /**
     * Returns the items between a set of date ranges of either the page date field (default) or
     * an arbitrary datetime page field where end date is optional
     * Dates can be passed in as text that strtotime() can process
     * http://php.net/manual/en/function.strtotime.php
     *
     * @param      $startDate
     * @param bool $endDate
     * @param      $field
     *
     * @return $this
     * @throws \Exception
     */
    public function dateRange($startDate, $endDate = false, $field = false)
    {
        $start = Utils::date2timestamp($startDate);
        $end = $endDate ? Utils::date2timestamp($endDate) : false;

        $date_range = [];
        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null) {
                $date = $field ? strtotime($page->value($field)) : $page->date();

                if ($date >= $start && (!$end || $date <= $end)) {
                    $date_range[$path] = $slug;
                }
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
     * Creates new collection with only modular pages
     *
     * @return Collection The collection with only modular pages
     */
    public function modular()
    {
        $modular = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->modular()) {
                $modular[$path] = $slug;
            }
        }
        $this->items = $modular;

        return $this;
    }

    /**
     * Creates new collection with only non-modular pages
     *
     * @return Collection The collection with only non-modular pages
     */
    public function nonModular()
    {
        $modular = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && !$page->modular()) {
                $modular[$path] = $slug;
            }
        }
        $this->items = $modular;

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
     * @param $type
     *
     * @return Collection The collection
     */
    public function ofType($type)
    {
        $items = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && $page->template() == $type) {
                $items[$path] = $slug;
            }
        }

        $this->items = $items;

        return $this;
    }

    /**
     * Creates new collection with only pages of one of the specified types
     *
     * @param $types
     *
     * @return Collection The collection
     */
    public function ofOneOfTheseTypes($types)
    {
        $items = [];

        foreach ($this->items as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page !== null && in_array($page->template(), $types)) {
                $items[$path] = $slug;
            }
        }

        $this->items = $items;

        return $this;
    }

    /**
     * Creates new collection with only pages of one of the specified access levels
     *
     * @param $accessLevels
     *
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
                        $items[$path] = $slug;
                    }
                } else {
                    //Single value for access
                    if (in_array($page->header()->access, $accessLevels)) {
                        $items[$path] = $slug;
                    }
                }

            }
        }

        $this->items = $items;

        return $this;
    }
}
