<?php
namespace Grav\Common;

/**
 * Class GravTrait
 *
 * @package Grav\Common
 */
trait GravTrait
{
    /**
     * @return Grav
     */
    public static function getGrav()
    {
        return Grav::instance();
    }
}

