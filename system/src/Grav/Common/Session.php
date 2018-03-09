<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use RocketTheme\Toolbox\Session\Session as BaseSession;

class Session extends BaseSession
{
    /** @var bool */
    protected $autoStart = false;

    /**
     * @param int    $lifetime Defaults to 1800 seconds.
     * @param string $path     Cookie path.
     * @param string $domain   Optional, domain for the session
     * @throws \RuntimeException
     */
    public function __construct($lifetime, $path, $domain = null)
    {
        if (php_sapi_name() !== 'cli') {
            parent::__construct($lifetime, $path, $domain);
        }
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
     * @param bool $secure
     * @return $this
     */
    public function setSecure($secure)
    {
        ini_set('session.cookie_secure', (bool)$secure);

        return $this;
    }

    /**
     * @param bool $httponly
     * @return $this
     */
    public function setHttpOnly($httponly)
    {
        ini_set('session.cookie_httponly', (bool)$httponly);

        return $this;
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
