<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

/**
 * @deprecated 2.0
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

        $caller = self::$grav['debugger']->getCaller();
        self::$grav['debugger']->addMessage("Deprecated GravTrait used in {$caller['file']}", 'deprecated');

        return self::$grav;
    }
}
