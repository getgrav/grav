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
        if (!function_exists('shell_exec')) {
            return "bin/composer.phar";
        }

        // check for global composer install
        $path = trim(shell_exec("command -v composer"));

        // fall back to grav bundled composer
        if (!$path || !preg_match('/(composer|composer\.phar)$/', $path)) {
            $path =  "bin/composer.phar";
        }

        return $path;
    }
}
