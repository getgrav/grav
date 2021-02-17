<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\Iterator;

/**
 * Class AbstractCollection
 * @package Grav\Common\GPM
 */
abstract class AbstractCollection extends Iterator
{
    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $items = [];

        foreach ($this->items as $name => $package) {
            $items[$name] = $package->toArray();
        }

        return $items;
    }
}
