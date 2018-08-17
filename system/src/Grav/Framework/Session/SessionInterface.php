<?php
/**
 * @package    Grav\Framework\Session
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Session;

/**
 * Class Session
 * @package Grav\Framework\Session
 */
interface SessionInterface extends \IteratorAggregate
{
    /**
     * Get current session instance.
     *
     * @return Session
     * @throws \RuntimeException
     */
    public static function getInstance();

    /**
     * Get session ID
     *
     * @return string|null Session ID
     */
    public function getId();

    /**
     * Set session ID
     *
     * @param string $id Session ID
     *
     * @return $this
     */
    public function setId($id);

    /**
     * Get session name
     *
     * @return string|null
     */
    public function getName();

    /**
     * Set session name
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name);

    /**
     * Sets session.* ini variables.
     *
     * @param array $options
     *
     * @see http://php.net/session.configuration
     */
    public function setOptions(array $options);

    /**
     * Starts the session storage
     *
     * @param bool $readonly
     * @return $this
     * @throws \RuntimeException
     */
    public function start($readonly = false);

    /**
     * Invalidates the current session.
     *
     * @return $this
     */
    public function invalidate();

    /**
     * Force the session to be saved and closed
     *
     * @return $this
     */
    public function close();

    /**
     * Free all session variables.
     *
     * @return $this
     */
    public function clear();

    /**
     * Returns all session variables.
     *
     * @return array
     */
    public function getAll();

    /**
     * Retrieve an external iterator
     *
     * @return \ArrayIterator Return an ArrayIterator of $_SESSION
     */
    public function getIterator();

    /**
     * Checks if the session was started.
     *
     * @return Boolean
     */
    public function isStarted();

    /**
     * Checks if session variable is defined.
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name);

    /**
     * Returns session variable.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name);

    /**
     * Sets session variable.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value);

    /**
     * Removes session variable.
     *
     * @param string $name
     */
    public function __unset($name);
}
