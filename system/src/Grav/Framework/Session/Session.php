<?php

/**
 * @package    Grav\Framework\Session
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Session;

use ArrayIterator;
use Exception;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Session\Exceptions\SessionException;
use RuntimeException;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Class Session
 * @package Grav\Framework\Session
 */
class Session implements SessionInterface
{
    /** @var array */
    protected $options = [];
    /** @var bool */
    protected $started = false;

    /** @var Session */
    protected static $instance;

    /**
     * @inheritdoc
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            throw new RuntimeException("Session hasn't been initialized.", 500);
        }

        return self::$instance;
    }

    /**
     * Session constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        // Session is a singleton.
        if (\PHP_SAPI === 'cli') {
            self::$instance = $this;

            return;
        }

        if (null !== self::$instance) {
            throw new RuntimeException('Session has already been initialized.', 500);
        }

        // Destroy any existing sessions started with session.auto_start
        if ($this->isSessionStarted()) {
            session_unset();
            session_destroy();
        }

        // Set default options.
        $options += [
            'cache_limiter' => 'nocache',
            'use_trans_sid' => 0,
            'use_cookies' => 1,
            'lazy_write' => 1,
            'use_strict_mode' => 1
        ];

        $this->setOptions($options);

        session_register_shutdown();

        self::$instance = $this;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return session_id() ?: null;
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        session_id($id);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return session_name() ?: null;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        session_name($name);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setOptions(array $options)
    {
        if (headers_sent() || \PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        $allowedOptions = [
            'save_path' => true,
            'name' => true,
            'save_handler' => true,
            'gc_probability' => true,
            'gc_divisor' => true,
            'gc_maxlifetime' => true,
            'serialize_handler' => true,
            'cookie_lifetime' => true,
            'cookie_path' => true,
            'cookie_domain' => true,
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'use_strict_mode' => true,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'cookie_samesite' => true,
            'referer_check' => true,
            'cache_limiter' => true,
            'cache_expire' => true,
            'use_trans_sid' => true,
            'trans_sid_tags' => true,
            'trans_sid_hosts' => true,
            'sid_length' => true,
            'sid_bits_per_character' => true,
            'upload_progress.enabled' => true,
            'upload_progress.cleanup' => true,
            'upload_progress.prefix' => true,
            'upload_progress.name' => true,
            'upload_progress.freq' => true,
            'upload_progress.min-freq' => true,
            'lazy_write' => true
        ];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                // Allow nested options.
                foreach ($value as $key2 => $value2) {
                    $ckey = "{$key}.{$key2}";
                    if (isset($value2, $allowedOptions[$ckey])) {
                        $this->setOption($ckey, $value2);
                    }
                }
            } elseif (isset($value, $allowedOptions[$key])) {
                $this->setOption($key, $value);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function start($readonly = false)
    {
        if (\PHP_SAPI === 'cli') {
            return $this;
        }

        $sessionName = session_name();
        $sessionExists = isset($_COOKIE[$sessionName]);

        // Protection against invalid session cookie names throwing exception: http://php.net/manual/en/function.session-id.php#116836
        if ($sessionExists && !preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $_COOKIE[$sessionName])) {
            unset($_COOKIE[$sessionName]);
            $sessionExists = false;
        }

        $options = $this->options;
        if ($readonly) {
            $options['read_and_close'] = '1';
        }

        try {
            $success = @session_start($options);
            if (!$success) {
                $last = error_get_last();
                $error = $last ? $last['message'] : 'Unknown error';

                throw new RuntimeException($error);
            }

            // Handle changing session id.
            if ($this->__isset('session_destroyed')) {
                $newId = $this->__get('session_new_id');
                if (!$newId || $this->__get('session_destroyed') < time() - 300) {
                    // Should not happen usually. This could be attack or due to unstable network. Destroy this session.
                    $this->invalidate();

                    throw new RuntimeException('Obsolete session access.', 500);
                }

                // Not fully expired yet. Could be lost cookie by unstable network. Start session with new session id.
                session_write_close();

                // Start session with new session id.
                $useStrictMode = $options['use_strict_mode'] ?? 0;
                if ($useStrictMode) {
                    ini_set('session.use_strict_mode', '0');
                }
                session_id($newId);
                if ($useStrictMode) {
                    ini_set('session.use_strict_mode', '1');
                }

                $success = @session_start($options);
                if (!$success) {
                    $last = error_get_last();
                    $error = $last ? $last['message'] : 'Unknown error';

                    throw new RuntimeException($error);
                }
            }
        } catch (Exception $e) {
            throw new SessionException('Failed to start session: ' . $e->getMessage(), 500);
        }

        $this->started = true;
        $this->onSessionStart();

        $user = $this->__get('user');
        if ($user && (!$user instanceof UserInterface || (method_exists($user, 'isValid') && !$user->isValid()))) {
            $this->invalidate();

            throw new SessionException('Invalid User object, session destroyed.', 500);
        }

        // Extend the lifetime of the session.
        if ($sessionExists) {
            $this->setCookie();
        }

        return $this;
    }

    /**
     * Regenerate session id but keep the current session information.
     *
     * Session id must be regenerated on login, logout or after long time has been passed.
     *
     * @return $this
     * @since 1.7
     */
    public function regenerateId()
    {
        if (!$this->isSessionStarted()) {
            return $this;
        }

        // TODO: session_create_id() segfaults in PHP 7.3 (PHP bug #73461), remove phpstan rule when removing this one.
        if (PHP_VERSION_ID < 70400) {
            $newId = 0;
        } else {
            // Session id creation may fail with some session storages.
            $newId = @session_create_id() ?: 0;
        }

        // Set destroyed timestamp for the old session as well as pointer to the new id.
        $this->__set('session_destroyed', time());
        $this->__set('session_new_id', $newId);

        // Keep the old session alive to avoid lost sessions by unstable network.
        if (!$newId) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addMessage('Session fixation lost session detection is turned of due to server limitations.', 'warning');

            session_regenerate_id(false);
        } else {
            session_write_close();

            // Start session with new session id.
            $useStrictMode = $this->options['use_strict_mode'] ?? 0;
            if ($useStrictMode) {
                ini_set('session.use_strict_mode', '0');
            }
            session_id($newId);
            if ($useStrictMode) {
                ini_set('session.use_strict_mode', '1');
            }

            $this->removeCookie();

            $success = @session_start($this->options);
            if (!$success) {
                $last = error_get_last();
                $error = $last ? $last['message'] : 'Unknown error';

                throw new RuntimeException($error);
            }

            $this->onSessionStart();
        }

        // New session does not have these.
        $this->__unset('session_destroyed');
        $this->__unset('session_new_id');

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function invalidate()
    {
        $name = $this->getName();
        if (null !== $name) {
            $this->removeCookie();

            setcookie(
                session_name(),
                '',
                $this->getCookieOptions(-42000)
            );
        }

        if ($this->isSessionStarted()) {
            session_unset();
            session_destroy();
        }

        $this->started = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        if ($this->started) {
            session_write_close();
        }

        $this->started = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        session_unset();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAll()
    {
        return $_SESSION;
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        return new ArrayIterator($_SESSION);
    }

    /**
     * @inheritdoc
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * @inheritdoc
     */
    public function __isset($name)
    {
        return isset($_SESSION[$name]);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        return $_SESSION[$name] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    /**
     * @inheritdoc
     */
    public function __unset($name)
    {
        unset($_SESSION[$name]);
    }

    /**
     * http://php.net/manual/en/function.session-status.php#113468
     * Check if session is started nicely.
     * @return bool
     */
    protected function isSessionStarted()
    {
        return \PHP_SAPI !== 'cli' ? \PHP_SESSION_ACTIVE === session_status() : false;
    }

    protected function onSessionStart(): void
    {
    }

    /**
     * Store something in cookie temporarily.
     *
     * @param int|null $lifetime
     * @return array
     */
    public function getCookieOptions(int $lifetime = null): array
    {
        $params = session_get_cookie_params();

        return [
            'expires'  => time() + ($lifetime ?? $params['lifetime']),
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite']
        ];
    }

    /**
     * @return void
     */
    protected function setCookie(): void
    {
        $this->removeCookie();

        setcookie(
            session_name(),
            session_id(),
            $this->getCookieOptions()
        );
    }

    protected function removeCookie(): void
    {
        $search = " {$this->getName()}=";
        $cookies = [];
        $found = false;

        foreach (headers_list() as $header) {
            // Identify cookie headers
            if (strpos($header, 'Set-Cookie:') === 0) {
                // Add all but session cookie(s).
                if (!str_contains($header, $search)) {
                    $cookies[] = $header;
                } else {
                    $found = true;
                }
            }
        }

        // Nothing to do.
        if (false === $found) {
            return;
        }

        // Remove all cookies and put back all but session cookie.
        header_remove('Set-Cookie');
        foreach($cookies as $cookie) {
            header($cookie, false);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setOption($key, $value)
    {
        if (!is_string($value)) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else {
                $value = (string)$value;
            }
        }

        $this->options[$key] = $value;
        ini_set("session.{$key}", $value);
    }
}
