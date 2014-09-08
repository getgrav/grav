<?php
namespace Grav\Common\GPM\Remote;

class Package {
    public function __construct($package, $package_type = false) {
        $this->data = $package;
        if ($package_type) {
            $this->data->package_type = $package_type;
        }
    }

    public function getData() {
        return $this->data;
    }

    public function __get($key) {
        return $this->data->$key;
    }

    public function __toString() {
        return $this->toJson();
    }

    public function toJson() {
        return json_encode($this->data);
    }

    public function toArray() {
        return $this->data;
    }

}
