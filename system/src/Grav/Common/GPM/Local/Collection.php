<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\GravTrait;
use Grav\Common\Iterator;

class Collection extends Iterator
{
    use GravTrait;

    public function toJson()
    {
        $items = [];

        foreach ($this->items as $name => $theme) {
            $items[$name] = $theme->toArray();
        }

        return json_encode($items);
    }

    public function toArray()
    {
        $items = [];

        foreach ($this->items as $name => $theme) {
            $items[$name] = $theme->toArray();
        }

        return $items;
    }
}
