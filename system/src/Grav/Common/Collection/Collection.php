<?php
namespace Grav\Common\Collection;

use Doctrine\Common\Collections\ArrayCollection;

class Collection extends ArrayCollection implements CollectionInterface
{
    /**
     * Reverse the order of the items.
     *
     * @return static
     */
    public function reverse()
    {
        return $this->createFrom(array_reverse($this->toArray()));
    }

    /**
     * Shuffle items.
     *
     * @return static
     */
    public function shuffle()
    {
        $keys = $this->getKeys();
        shuffle($keys);

        return $this->createFrom(array_replace(array_flip($keys), $this->toArray()));
    }

    /**
     * Implementes JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
