<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * Object trait.
 *
 * @package Grav\Framework\Object
 */
trait ObjectTrait
{
    /**
     * Properties of the object.
     * @var array
     */
    protected $items;

    /**
     * @var string
     */
    private $key;

    /**
     * @param array $elements
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {

        $this->items = $elements;
        $this->key = $key !== null ? $key : $this->getKey();

        if ($this->key === null) {
            throw new \InvalidArgumentException('Object cannot be created without assigning a key');
        }
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . '@' . spl_object_hash($this);
    }
}
