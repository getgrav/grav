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
class Session implements SessionInterface
{
    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @var Session
     */
    protected static $instance;

    /**
     * @inheritdoc
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            throw new \RuntimeException("Session hasn't been initialized.", 500);
        }

        return self::$instance;
    }

    /**
     * @inheritdoc
     */
    public function __construct(array $options = [])
    {
        // Session is a singleton.
        if (\PHP_SAPI === 'cli') {
            self::$instance = $this;

            return;
        }

        if (null !== self::$instance) {
            throw new \RuntimeException('Session has already been initialized.', 500);
        }

        // Destroy any existing sessions started with session.auto_start
        if ($this->isSessionStarted()) {
            session_unset();
            session_destroy();
        }

        // Set default options.
        $options += array(
            'cache_limiter' => 'nocache',
            'use_trans_sid' => 0,
            'use_cookies' => 1,
            'lazy_write' => 1,
            'use_strict_mode' => 1
        );

        $this->setOptions($options);

        session_register_shutdown();

        self::$instance = $this;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return session_id();
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
        return session_name();
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
            'referer_check' => true,
            'cache_limiter' => true,
            'cache_expire' => true,
            'use_trans_sid' => true,
            'trans_sid_tags' => true,           // PHP 7.1
            'trans_sid_hosts' => true,          // PHP 7.1
            'sid_length' => true,               // PHP 7.1
            'sid_bits_per_character' => true,   // PHP 7.1
            'upload_progress.enabled' => true,
            'upload_progress.cleanup' => true,
            'upload_progress.prefix' => true,
            'upload_progress.name' => true,
            'upload_progress.freq' => true,
            'upload_progress.min-freq' => true,
            'lazy_write' => true,
            'url_rewriter.tags' => true,        // Not used in PHP 7.1
            'hash_function' => true,            // Not used in PHP 7.1
            'hash_bits_per_character' => true,  // Not used in PHP 7.1
            'entropy_file' => true,             // Not used in PHP 7.1
            'entropy_length' => true,           // Not used in PHP 7.1
        ];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                // Allow nested options.
                foreach ($value as $key2 => $value2) {
                    $ckey = "{$key}.{$key2}";
                    if (isset($value2, $allowedOptions[$ckey])) {
                        $this->ini_set("session.{$ckey}", $value2);
                    }
                }
            } elseif (isset($value, $allowedOptions[$key])) {
                $this->ini_set("session.{$key}", $value);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function start($readonly = false)
    {
        // Protection against invalid session cookie names throwing exception: http://php.net/manual/en/function.session-id.php#116836
        if (isset($_COOKIE[session_name()]) && !preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $_COOKIE[session_name()])) {
            unset($_COOKIE[session_name()]);
        }

        $options = $readonly ? ['read_and_close' => '1'] : [];

        $success = @session_start($options);
        if (!$success) {
            $last = error_get_last();
            $error = $last ? $last['message'] : 'Unknown error';
            throw new \RuntimeException('Failed to start session: ' . $error, 500);
        }

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            session_id(),
            time() + $params['lifetime'],
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

        $this->started = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function invalidate()
    {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

        session_unset();
        session_destroy();

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
        return new \ArrayIterator($_SESSION);
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
        return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
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

    /**
     * @param string $key
     * @param mixed $value
     */
    protected function ini_set($key, $value)
    {
        if (!is_string($value)) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $value = (string)$value;
        }

        ini_set($key, $value);
    }
}
