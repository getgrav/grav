<?php
/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\ArrayCollection as BaseArrayCollection;

/**
 * General JSON serializable collection.
 *
 * @package Grav\Framework\Collection
 */
class ArrayCollection extends BaseArrayCollection implements CollectionInterface
{
    /**
     * Reverse the order of the items.
     *
     * @return static
     */
    public function reverse()
    {
        if (method_exists($this, 'createFrom')) {
            return $this->createFrom(array_reverse($this->toArray()));
        } else {
            // TODO: remove when PHP 5.6 is minimum (with doctrine/collections v1.4).
            return new static(array_reverse($this->toArray()));
        }
    }

    /**
     * Shuffle items.
     *
     * @return static
     */
    public function shuffle()
    {
        $keys = $this->getKeys();
        shuffle($keys);

        if (method_exists($this, 'createFrom')) {
            return $this->createFrom(array_replace(array_flip($keys), $this->toArray()));
        } else {
            // TODO: remove when PHP 5.6 is minimum (with doctrine/collections v1.4).
            return new static(array_replace(array_flip($keys), $this->toArray()));
        }
    }

    /**
     * Implementes JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
