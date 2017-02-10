<?php
namespace Grav\Common\Object;

use Grav\Common\Collection\AbstractCollection;
use Grav\Common\Collection\ObjectCollectionInterface;
use Grav\Common\Collection\ObjectCollectionTrait;

class ObjectCollection extends AbstractCollection implements ObjectCollectionInterface
{
    use ObjectCollectionTrait;

    /**
     * Collection constructor.
     * @param array|AbstractObject[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }
}
