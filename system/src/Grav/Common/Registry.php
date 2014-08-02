<?php
namespace Grav\Common;

/**
 * The Registry class is an implementation of the Registry Pattern to store and retrieve
 * instances of objects used by Grav
 *
 * @author RocketTheme
 * @license MIT
 */
class Registry
{
    /**
     * @var array
     */
    private $registry = array();

    /**
     * @var Registry
     */
    private static $instance = null;

    /**
     * Return global instance.
     *
     * @return Registry
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new Registry();
        }

        return self::$instance;
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
        if (!isset(self::$instance->registry[$key])) {
            throw new \Exception("There is no entry for key " . $key);
        }

        return self::$instance->registry[$key];
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
        if (isset($this->registry[$key])) {
            throw new \Exception("There is already an entry for key " . $key);
        }

        $this->registry[$key] = $value;
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
        if (!isset($this->registry[$key])) {
            throw new \Exception("There is no entry for key " . $key);
        }

        return $this->registry[$key];
    }
}
