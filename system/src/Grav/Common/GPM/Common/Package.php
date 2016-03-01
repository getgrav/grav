<?php
namespace Grav\Common\GPM\Common;

use Grav\Common\Data\Data;

/**
 * @property string name
 * @property string version
 * @property string available
 * @property string package_type
 * @property string description_plain
 * @property string slug
 * @property array author
 * @property mixed changelog
 */

class Package {

    protected $data;

    public function __construct(Data $package, $type = null) {
        $this->data = $package;

        if ($type) {
            $this->data->set('package_type', $type);
        }
    }

    public function getData() {
        return $this->data;
    }

    public function __get($key) {
        return $this->data->get($key);
    }

    public function __isset($key) {
        return isset($this->data->$key);
    }

    public function __toString() {
        return $this->toJson();
    }

    public function toJson() {
        return $this->data->toJson();
    }

    public function toArray() {
        return $this->data->toArray();
    }

}
