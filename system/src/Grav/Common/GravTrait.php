<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

/**
 * @deprecated 1.4 Use Grav::instance() instead
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

        user_error(__TRAIT__ . ' is deprecated since Grav 1.4, use Grav::instance() instead', E_USER_DEPRECATED);

        return self::$grav;
    }
}
