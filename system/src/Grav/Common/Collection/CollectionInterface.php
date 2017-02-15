<?php
namespace Grav\Common\Collection;

use Doctrine\Common\Collections\Collection;

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
