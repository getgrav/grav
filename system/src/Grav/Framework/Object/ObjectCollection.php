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

    static protected $prefix = 'c.';

    /**
     * @param array $elements
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        parent::__construct($elements);

        $this->key = $key !== null ? $key : (string) $this;

        if ($this->key === null) {
            throw new \InvalidArgumentException('Object cannot be created without assigning a key to it');
        }
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
}
