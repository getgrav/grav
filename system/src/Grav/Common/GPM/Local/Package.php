<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\Data\Data;

class Package {
    protected $data;
    protected $blueprints;

    public function __construct(Data $package, $package_type = false) {
        $this->data       = $package;
        $this->blueprints = $this->data->blueprints();

        if ($package_type) {
            $this->blueprints->set('package_type', $package_type);
        }
    }

    public function isEnabled() {
        return $this->data['enabled'];
    }

    public function getData() {
        return $this->data;
    }

    public function __get($key) {
        return $this->blueprints->get($key);
    }

    public function __toString() {
        return $this->toJson();
    }

    public function toJson() {
        return $this->blueprints->toJson();
    }

    public function toArray() {
        return $this->blueprints->toArray();
    }

}
