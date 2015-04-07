<?php
namespace Grav\Common\GPM;

use Grav\Common\GravTrait;
use Grav\Common\Iterator;

abstract class AbstractCollection extends Iterator {

    use GravTrait;

    public function toJson()
    {
        $items = [];

        foreach ($this->items as $name => $package) {
            $items[$name] = $package->toArray();
        }

        return json_encode($items);
    }

    public function toArray()
    {
        $items = [];

        foreach ($this->items as $name => $package) {
            $items[$name] = $package->toArray();
        }

        return $items;
    }
}
