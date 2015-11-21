<?php

namespace Grav\Common\GPM\Local;

use Grav\Common\GPM\Common\AbstractPackageCollection as BaseCollection;

abstract class AbstractPackageCollection extends BaseCollection
{
    public function __construct($items)
    {
        foreach ($items as $name => $data) {
            $data->set('slug', $name);
            $this->items[$name] = new Package($data, $this->type);
        }
    }
}
