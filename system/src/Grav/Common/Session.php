<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

class Session extends \Grav\Framework\Session\Session
{
    /** @var bool */
    protected $autoStart = false;

    /**
     * @return \Grav\Framework\Session\Session
     * @deprecated 1.5
     */
    public static function instance()
    {
        return static::getInstance();
    }

    /**
     * Initialize session.
     *
     * Code in this function has been moved into SessionServiceProvider class.
     */
    public function init()
    {
        if ($this->autoStart) {
            $this->start();

            $this->autoStart = false;
        }
    }

    /**
     * @param bool $auto
     * @return $this
     */
    public function setAutoStart($auto)
    {
        $this->autoStart = (bool)$auto;

        return $this;
    }

    /**
     * Returns attributes.
     *
     * @return array Attributes
     * @deprecated 1.5
     */
    public function all()
    {
        return $this->getAll();
    }

    /**
     * Checks if the session was started.
     *
     * @return Boolean
     * @deprecated 1.5
     */
    public function started()
    {
        return $this->isStarted();
    }

    /**
     * Store something in session temporarily.
     *
     * @param string $name
     * @param mixed $object
     * @return $this
     */
    public function setFlashObject($name, $object)
    {
        $this->{$name} = serialize($object);

        return $this;
    }

    /**
     * Return object and remove it from session.
     *
     * @param string $name
     * @return mixed
     */
    public function getFlashObject($name)
    {
        $object = unserialize($this->{$name});

        $this->{$name} = null;

        return $object;
    }

    /**
     * Store something in cookie temporarily.
     *
     * @param string $name
     * @param mixed $object
     * @param int $time
     * @return $this
     */
    public function setFlashCookieObject($name, $object, $time = 60)
    {
        setcookie($name, json_encode($object), time() + $time, '/');

        return $this;
    }

    /**
     * Return object and remove it from the cookie.
     *
     * @param string $name
     * @return mixed|null
     */
    public function getFlashCookieObject($name)
    {
        if (isset($_COOKIE[$name])) {
            $object = json_decode($_COOKIE[$name]);
            setcookie($name, '', time() - 3600, '/');
            return $object;
        }

        return null;
    }
}
