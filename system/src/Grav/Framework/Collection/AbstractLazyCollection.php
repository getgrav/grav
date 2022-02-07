<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\AbstractLazyCollection as BaseAbstractLazyCollection;

/**
 * General JSON serializable collection.
 *
 * @package Grav\Framework\Collection
 * @template TKey of array-key
 * @template T
 * @extends BaseAbstractLazyCollection<TKey,T>
 * @implements CollectionInterface<TKey,T>
 */
abstract class AbstractLazyCollection extends BaseAbstractLazyCollection implements CollectionInterface
{
    /**
     * @par ArrayCollection
     * @phpstan-var ArrayCollection<TKey,T>
     */
    protected $collection;

    /**
     * {@inheritDoc}
     * @phpstan-return ArrayCollection<TKey,T>
     */
    public function reverse()
    {
        $this->initialize();

        return $this->collection->reverse();
    }

    /**
     * {@inheritDoc}
     * @phpstan-return ArrayCollection<TKey,T>
     */
    public function shuffle()
    {
        $this->initialize();

        return $this->collection->shuffle();
    }

    /**
     * {@inheritDoc}
     */
    public function chunk($size)
    {
        $this->initialize();

        return $this->collection->chunk($size);
    }

    /**
     * {@inheritDoc}
     * @phpstan-param array<TKey,T> $keys
     * @phpstan-return ArrayCollection<TKey,T>
     */
    public function select(array $keys)
    {
        $this->initialize();

        return $this->collection->select($keys);
    }

    /**
     * {@inheritDoc}
     * @phpstan-param array<TKey,T> $keys
     * @phpstan-return ArrayCollection<TKey,T>
     */
    public function unselect(array $keys)
    {
        $this->initialize();

        return $this->collection->unselect($keys);
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $this->initialize();

        return $this->collection->jsonSerialize();
    }
}
