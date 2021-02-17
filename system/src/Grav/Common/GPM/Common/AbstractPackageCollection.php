<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Common;

use Grav\Common\Iterator;

/**
 * Class AbstractPackageCollection
 * @package Grav\Common\GPM\Common
 */
abstract class AbstractPackageCollection extends Iterator
{
    /** @var string */
    protected $type;

    /**
     * @return string
     */
    public function toJson()
    {
        $items = [];

        foreach ($this->items as $name => $package) {
            $items[$name] = $package->toArray();
        }

        return json_encode($items, JSON_THROW_ON_ERROR);
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
