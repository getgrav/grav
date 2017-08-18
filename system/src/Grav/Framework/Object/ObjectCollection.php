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
 * Object Collection
 * @package Grav\Framework\Object
 */
class ObjectCollection extends ArrayCollection implements ObjectCollectionInterface
{
    use ObjectCollectionTrait;

    /**
     * @param array $elements
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        parent::__construct($elements);

        $this->key = $key ?: $this->getKey() ?: __CLASS__ . '@' . spl_object_hash($this);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return ['key' => $this->getKey(), 'objects' => $this->toArray()];
    }
}
