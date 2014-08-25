<?php
/**
 * Class Minify_Logger  
 * @package Minify
 */

/** 
 * Message logging class
 * 
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 *
 * @todo lose this singleton! pass log object in Minify::serve and distribute to others
 */
class Minify_Logger {

    /**
     * Set logger object. 
     *
     * The object should have a method "log" that accepts a value as 1st argument and
     * an optional string label as the 2nd.
     *
     * @param mixed $obj or a "falsey" value to disable
     * @return null
     */
    public static function setLogger($obj = null) {
        self::$_logger = $obj
            ? $obj
            : null;
    }
    
    /**
     * Pass a message to the logger (if set)
     *
     * @param string $msg message to log
     * @return null
     */
    public static function log($msg, $label = 'Minify') {
        if (! self::$_logger) return;
        self::$_logger->log($msg, $label);
    }
    
    /**
     * @var mixed logger object (like FirePHP) or null (i.e. no logger available)
     */
    private static $_logger = null;
}
