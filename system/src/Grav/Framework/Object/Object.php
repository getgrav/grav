<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccessWithGetters;
use RocketTheme\Toolbox\ArrayTraits\Export;

/**
 * Object class.
 *
 * @package Grav\Framework\Object
 */
class Object implements ObjectInterface
{
    use ObjectTrait, ArrayAccessWithGetters, Export;

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
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return ['key' => $this->getKey(), 'object' => $this->toArray()];
    }
}
