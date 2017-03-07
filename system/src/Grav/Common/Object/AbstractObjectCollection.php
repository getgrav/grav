<?php
namespace Grav\Common\Object;

use Grav\Common\Collection\Collection;
use Grav\Common\Collection\ObjectCollectionInterface;
use Grav\Common\Collection\ObjectCollectionTrait;

abstract class AbstractObjectCollection extends Collection implements ObjectCollectionInterface
{
    use ObjectCollectionTrait, ObjectStorageTrait;

    protected $id;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }
}
