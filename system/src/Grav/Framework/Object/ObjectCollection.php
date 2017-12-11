<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use Grav\Framework\Collection\ArrayCollection;
use Grav\Framework\Object\Access\NestedPropertyCollectionTrait;
use Grav\Framework\Object\Base\ObjectCollectionTrait;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;

/**
 * Object Collection
 * @package Grav\Framework\Object
 */
class ObjectCollection extends ArrayCollection implements ObjectCollectionInterface, NestedObjectInterface
{
    use ObjectCollectionTrait, NestedPropertyCollectionTrait {
        NestedPropertyCollectionTrait::group insteadof ObjectCollectionTrait;
    }

    /**
     * @param array $elements
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        parent::__construct($this->setElements($elements));

        $this->setKey($key);
    }

    protected function getElements()
    {
        return $this->toArray();
    }

    protected function setElements(array $elements)
    {
        return $elements;
    }
}
