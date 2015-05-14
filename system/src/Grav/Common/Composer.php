<?php

namespace Grav\Common;

/**
 * Offers composer helper methods.
 *
 * @author  eschmar
 * @license MIT
 */
class Composer
{
    /**
     * Returns the location of composer.
     *
     * @return string
     */
    public static function getComposerLocation()
    {
        // check for global composer install
        $path = shell_exec("which composer");

        // fall back to grav bundled composer
        if (!$path || !preg_match('/(composer|composer\.phar)$/', $path)) {
            $path =  "bin/composer.phar";
        }

        return $path;
    }
}
