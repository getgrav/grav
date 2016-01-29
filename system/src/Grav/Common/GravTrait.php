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
     * @var Grav
     */
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

    /**
     * @param Grav $grav
     */
    public static function setGrav(Grav $grav)
    {
        self::$grav = $grav;
    }
}
