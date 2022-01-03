<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\ArrayCollection as BaseArrayCollection;

/**
 * General JSON serializable collection.
 *
 * @package Grav\Framework\Collection
 * @template TKey of array-key
 * @template T
 * @extends BaseArrayCollection<TKey,T>
 * @implements CollectionInterface<TKey,T>
 */
class ArrayCollection extends BaseArrayCollection implements CollectionInterface
{
    /**
     * Reverse the order of the items.
     *
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function reverse()
    {
        $keys = array_reverse($this->toArray());

        /** @phpstan-var static<TKey,T> */
        return $this->createFrom($keys);
    }

    /**
     * Shuffle items.
     *
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function shuffle()
    {
        $keys = $this->getKeys();
        shuffle($keys);
        $keys = array_replace(array_flip($keys), $this->toArray());

        /** @phpstan-var static<TKey,T> */
        return $this->createFrom($keys);
    }

    /**
     * Split collection into chunks.
     *
     * @param int $size     Size of each chunk.
     * @return array
     * @phpstan-return array<array<TKey,T>>
     */
    public function chunk($size)
    {
        /** @phpstan-var array<array<TKey,T>> */
        return array_chunk($this->toArray(), $size, true);
    }

    /**
     * Select items from collection.
     *
     * Collection is returned in the order of $keys given to the function.
     *
     * @param array<int,string> $keys
     * @return static
     * @phpstan-param TKey[] $keys
     * @phpstan-return static<TKey,T>
     */
    public function select(array $keys)
    {
        $list = [];
        foreach ($keys as $key) {
            if ($this->containsKey($key)) {
                $list[$key] = $this->get($key);
            }
        }

        /** @phpstan-var static<TKey,T> */
        return $this->createFrom($list);
    }

    /**
     * Un-select items from collection.
     *
     * @param array<int|string> $keys
     * @return static
     * @phpstan-param TKey[] $keys
     * @phpstan-return static<TKey,T>
     */
    public function unselect(array $keys)
    {
        $list = array_diff($this->getKeys(), $keys);

        /** @phpstan-var static<TKey,T> */
        return $this->select($list);
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
