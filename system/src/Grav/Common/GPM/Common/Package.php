<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Common;

use Grav\Common\Data\Data;

/**
 * @property string $name
 */
class Package
{
    /** @var Data */
    protected $data;

    /**
     * Package constructor.
     * @param Data $package
     * @param string|null $type
     */
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

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->data->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->data->set($key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->data->{$key});
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return string
     */
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
