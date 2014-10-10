<?php
namespace Grav\Common\Page;

use Grav\Common\Grav;
use Grav\Common\Iterator;

/**
 * Collection of Pages.
 *
 * @author RocketTheme
 * @license MIT
 */
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

    public function __construct($items = array(), array $params = array(), Pages $pages = null) {
        parent::__construct($items);

        $this->params = $params;
        $this->pages = $pages ? $pages : Grav::instance()->offsetGet('pages');
    }

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
     * @param mixed $offset  The offset to retrieve.
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
    }

    /**
     * Reorder collection.
     *
     * @param string $by
     * @param string $dir
     * @param array  $manual
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
     * @return boolean True if item is last.
     */
    public function isLast($path)
    {
        if ($this->items && $path == array_keys($this->items)[count($this->items)-1]) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @param  string  $path
     * @return Page  The previous item.
     */
    public function prevSibling($path)
    {
        return $this->adjacentSibling($path, -1);
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @param  string  $path
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
     * @return Page    The sibling item.
     */
    public function adjacentSibling($path, $direction = 1)
    {

        $values = array_keys($this->items);
        $keys = array_flip($values);
        $index = $keys[$path] - $direction;

        return isset($values[$index]) ? $this->offsetGet($values[$index]) : $this;
    }

    /**
     * Returns the item in the current position.
     *
     * @param  string  $path  the path the item
     * @return Page    Item in the array the the current position.
     */
    public function currentPosition($path) {
        return array_search($path, array_keys($this->items));
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
            if ($page->visible()) {
               $visible[$path] = $slug;
            }
        }
        return new static($visible, $this->params, $this->pages);
    }

    /**
     * Creates new collection with only routable pages
     *
     * @return Collection The collection with only routable pages
     */
    public function routable()
    {
        $routable = [];

        foreach (array_keys($this->items) as $path => $slug) {
            $page = $this->pages->get($path);
            if ($page->routable()) {
                $routable[$path] = $slug;
            }
        }
        return new static($routable, $this->params, $this->pages);
    }
}
