<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\Collection;

/**
 * Collection Interface.
 *
 * @package Grav\Framework\Collection
 */
interface CollectionInterface extends Collection, \JsonSerializable
{
    /**
     * Reverse the order of the items.
     *
     * @return CollectionInterface
     */
    public function reverse();

    /**
     * Shuffle items.
     *
     * @return CollectionInterface
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
     */
    public function select(array $keys);

    /**
     * Un-select items from collection.
     *
     * @param array<int|string> $keys
     * @return CollectionInterface
     */
    public function unselect(array $keys);
}
