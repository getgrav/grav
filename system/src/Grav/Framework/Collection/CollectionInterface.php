<?php
/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
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
}
