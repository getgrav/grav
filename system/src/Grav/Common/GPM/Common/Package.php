<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Common;

use Grav\Common\Data\Data;

class Package
{
    /**
     * @var Data
     */
    protected $data;

    public function __construct(Data $package, $type = null)
    {
        $this->data = $package;

        if ($type) {
            $this->data->set('package_type', $type);
        }
    }

    /**
     * @return Data
     */
    public function getData()
    {
        return $this->data;
    }

    public function __get($key)
    {
        return $this->data->get($key);
    }

    public function __set($key, $value)
    {
        return $this->data->set($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->data->{$key});
    }

    public function __toString()
    {
        return $this->toJson();
    }

    public function toJson()
    {
        return $this->data->toJson();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data->toArray();
    }
}
