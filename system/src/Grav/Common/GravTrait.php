<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

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

