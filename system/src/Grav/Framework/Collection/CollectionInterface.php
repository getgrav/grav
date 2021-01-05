<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\Collection;
use JsonSerializable;

/**
 * Collection Interface.
 *
 * @package Grav\Framework\Collection
 * @template TKey
 * @template T
 * @extends Collection<TKey,T>
 */
interface CollectionInterface extends Collection, JsonSerializable
{
    /**
     * Reverse the order of the items.
     *
     * @return CollectionInterface
     * @phpstan-return CollectionInterface<TKey,T>
     */
    public function reverse();

    /**
     * Shuffle items.
     *
     * @return CollectionInterface
     * @phpstan-return CollectionInterface<TKey,T>
     */
    public function shuffle();

    /**
     * Split collection into chunks.
     *
     * @param int $size     Size of each chunk.
     * @return array
     */
    public function chunk($size);

    /**
     * Select items from collection.
     *
     * Collection is returned in the order of $keys given to the function.
     *
     * @param array<int|string> $keys
     * @return CollectionInterface
     * @phpstan-return CollectionInterface<TKey,T>
     */
    public function select(array $keys);

    /**
     * Un-select items from collection.
     *
     * @param array<int|string> $keys
     * @return CollectionInterface
     * @phpstan-return CollectionInterface<TKey,T>
     */
    public function unselect(array $keys);
}
