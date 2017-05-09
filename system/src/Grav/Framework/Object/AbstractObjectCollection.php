<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use Grav\Framework\Collection\ArrayCollection;

/**
 * Abstract Object Collection
 * @package Grav\Framework\Object
 */
abstract class AbstractObjectCollection extends ArrayCollection implements ObjectCollectionInterface, StoredObjectInterface
{
    use ObjectStorageTrait {
        getId as getParentId;
    }
    use ObjectCollectionTrait;

    protected $id;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id ?: $this->getParentId();
    }
}
