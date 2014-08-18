<?php
namespace Grav\Common;

/**
 * The Registry class is an implementation of the Registry Pattern to store and retrieve
 * instances of objects used by Grav
 *
 * @author RocketTheme
 * @license MIT
 * @deprecated
 */
class Registry
{

    /**
     * Return global instance.
     *
     * @return Registry
     */
    public static function instance()
    {
        user_error(__METHOD__ . '()', E_USER_DEPRECATED);
        return new Registry;
    }

    /**
     * Get entry from the registry.
     *
     * @param string $key
     * @return mixed
     * @throws \Exception
     */
    public static function get($key)
    {
        user_error(__METHOD__ . '()', E_USER_DEPRECATED);
        $instance = Grav::instance();
        return $instance[$key];
    }

    /**
     * @internal
     */
    private function __construct()
    {
    }

    /**
     * @internal
     */
    private function __clone()
    {
    }

    /**
     * Store entry to the registry.
     *
     * @param string $key
     * @param mixed  $value
     * @throws \Exception
     */
    public function store($key, $value)
    {
        user_error(__CLASS__ . '::' . __METHOD__ . '()', E_USER_DEPRECATED);
        $instance = Grav::instance();
        $instance[$key] = $value;
    }

    /**
     * Get entry from the registry.
     *
     * @param  string $key
     * @return mixed
     * @throws \Exception
     */
    public function retrieve($key)
    {
        user_error(__CLASS__ . '::' . __METHOD__ . '()', E_USER_DEPRECATED);
        $instance = Grav::instance();
        return $instance[$key];
    }
}
