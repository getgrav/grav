<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
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
        return $this->createFrom(array_reverse($this->toArray()));
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

        return $this->createFrom(array_replace(array_flip($keys), $this->toArray()) ?? []);
    }

    /**
     * Split collection into chunks.
     *
     * @param int $size     Size of each chunk.
     * @return array
     */
    public function chunk($size)
    {
        return array_chunk($this->toArray(), $size, true);
    }

    /**
     * Select items from collection.
     *
     * Collection is returned in the order of $keys given to the function.
     *
     * @param array<int|string> $keys
     * @return static
     * @phpstan-param array<TKey> $keys
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

        return $this->createFrom($list);
    }

    /**
     * Un-select items from collection.
     *
     * @param array<int|string> $keys
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function unselect(array $keys)
    {
        return $this->select(array_diff($this->getKeys(), $keys));
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
