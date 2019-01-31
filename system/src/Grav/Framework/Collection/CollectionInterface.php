<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
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
     * @return static
     */
    public function reverse();

    /**
     * Shuffle items.
     *
     * @return static
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
     * @param array $keys
     * @return static
     */
    public function select(array $keys);

    /**
     * Un-select items from collection.
     *
     * @param array $keys
     * @return static
     */
    public function unselect(array $keys);
}
