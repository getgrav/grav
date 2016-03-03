<?php
namespace Grav\Common;

/**
 * Class GravTrait
 *
 * @package Grav\Common
 * @deprecated
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

