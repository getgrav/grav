<?php
namespace Grav\Common\Object;

use Grav\Common\Collection\Collection;
use Grav\Common\Collection\ObjectCollectionInterface;
use Grav\Common\Collection\ObjectCollectionTrait;

class ObjectCollection extends Collection implements ObjectCollectionInterface
{
    use ObjectCollectionTrait;
}
