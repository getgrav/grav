<?php
namespace Grav\Common\Object;

use Grav\Common\Collection\CollectionInterface;

interface ObjectCollectionInterface extends CollectionInterface
{
    /**
     *
     * Create a clone from this collection.
     *
     * @return static
     */
    public function getClone();
}
