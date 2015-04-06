<?php
namespace Grav\Common\GPM;

use Grav\Common\Data\Data;

/**
 * Interface Package
 * @package Grav\Common\GPM
 */
class Package
{
    /**
     * @var Data
     */
    protected $data;

    /**
     * @var \Grav\Common\Data\Blueprint
     */
    protected $blueprints;

    /**
     * @param Data $package
     * @param bool $package_type
     */
    public function __construct(Data $package, $package_type = false);

    /**
     * @return mixed
     */
    public function isEnabled();

    /**
     * @return Data
     */
    public function getData();

    /**
     * @param $key
     * @return mixed
     */
    public function __get($key);

    /**
     * @return string
     */
    public function __toString();

    /**
     * @return string
     */
    public function toJson();

    /**
     * @return array
     */
    public function toArray();
}
