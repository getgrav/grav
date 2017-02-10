<?php
namespace Grav\Common\Collection;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;

trait SortingCollectionTrait
{
    use ArrayAccess;

    /**
     * Reverse the order of the items.
     *
     * @return $this
     */
    public function reverse()
    {
        $this->items = array_reverse($this->items);

        return $this;
    }

    /**
     * Shuffle items.
     *
     * @return $this
     */
    public function shuffle()
    {
        $keys = array_keys($this->items);
        shuffle($keys);

        $this->items = array_replace(array_flip($keys), $this->items);

        return $this;
    }

    /**
     * Sort collection by values using a user-defined comparison function.
     *
     * @param callable  $callback
     * @return $this
     */
    public function sort(callable $callback)
    {
        uasort($this->items, $callback);

        return $this;
    }
    /**
     * Sort collection by keys.
     *
     * @return $this
     */
    public function ksort($sort_flags = SORT_REGULAR)
    {
        ksort($this->items, $sort_flags);

        return $this;
    }


    /**
     * Sort collection by keys in reverse order.
     *
     * @return $this
     */
    public function krsort($sort_flags = SORT_REGULAR)
    {
        krsort($this->items, $sort_flags);

        return $this;
    }

    /**
     * Sort collection by keys using a user-defined comparison function.
     *
     * @return $this
     */
    public function uksort(callable $key_compare_func)
    {
        uksort($this->items, $key_compare_func);

        return $this;
    }}
