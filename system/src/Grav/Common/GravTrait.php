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
    protected static $grav;

    /**
     * @return Grav
     */
    public static function getGrav()
    {
        if (!self::$grav) {
            self::$grav = Grav::instance();
        }
        return self::$grav;
    }
}

