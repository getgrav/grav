<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Form\FormFlash;
use Grav\Events\BeforeSessionStartEvent;
use Grav\Events\SessionStartEvent;
use Grav\Plugin\Form\Forms;
use JsonException;
use function is_string;

/**
 * Class Session
 * @package Grav\Common
 */
class Session extends \Grav\Framework\Session\Session
{
    /** @var bool */
    protected $autoStart = false;
    /** @var bool Start the session only for a request that brings a session cookie, or on the first write (system.session.lazy). */
    protected $lazy = false;
    /** @var bool The request had no session cookie, so starting the session waits for the first write. */
    protected $pending = false;

    /**
     * @return \Grav\Framework\Session\Session
     * @deprecated 1.5 Use ->getInstance() method instead.
     */
    public static function instance()
    {
        user_error(self::class . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use ->getInstance() method instead', E_USER_DEPRECATED);

        return static::getInstance();
    }

    /**
     * Initialize session.
     *
     * Code in this function has been moved into SessionServiceProvider class.
     *
     * @return void
     */
    public function init()
    {
        if ($this->autoStart && !$this->started && !$this->pending) {
            $this->autoStart = false;

            // Opt-in (system.session.lazy): a visitor without a session cookie has no session
            // to resume, so don't create one until something is stored in it. Until then the
            // response carries no session cookie, which lets a proxy or CDN cache the page.
            if ($this->lazy && !$this->hasSessionCookie()) {
                $this->pending = true;

                return;
            }

            $this->startNow();
        }
    }

    /**
     * Start the session only when the request brings a session cookie or when something is
     * first stored in it. Set from system.session.lazy.
     *
     * @param bool $lazy
     * @return $this
     */
    public function setLazy(bool $lazy)
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * True while the session waits for its first write to start (see setLazy()). The session
     * counts as started meanwhile: it can be read (it is empty) and written to.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->pending;
    }

    /**
     * Start a session that waits for its first write. Does nothing otherwise.
     *
     * @return $this
     */
    public function startPending()
    {
        if ($this->pending) {
            $this->pending = false;

            // Too late to send the cookie: keep what this request stores in memory only.
            if (headers_sent()) {
                $_SESSION ??= [];
                Grav::instance()['debugger']->addMessage('Session started after the response was sent: session data will not be kept.', 'warning');

                return $this;
            }

            $this->startNow();
        }

        return $this;
    }

    /**
     * Asking for the id starts a waiting session: callers key files and caches by it
     * (form uploads, per-session Flex caches), so every visitor needs their own.
     *
     * @inheritdoc
     */
    public function getId()
    {
        $this->startPending();

        return parent::getId();
    }

    /**
     * @return void
     */
    protected function startNow(): void
    {
        // Opt-in: start the session read-only so its exclusive lock is
        // released right after the initial read. The first write transparently
        // re-acquires it. Lets requests sharing a session id run concurrently
        // instead of serializing on the lock (e.g. the SPA admin's parallel
        // API calls). Default off — read-modify-write across the request is no
        // longer atomic, which is fine for typical session use but is a
        // behaviour change, so it stays opt-in.
        $readonly = (bool) Grav::instance()['config']->get('system.session.read_and_close', false);
        $this->start($readonly);
    }

    /**
     * @return bool
     */
    protected function hasSessionCookie(): bool
    {
        $name = $this->getName();

        return null !== $name && isset($_COOKIE[$name]) && $_COOKIE[$name] !== '';
    }

    /**
     * @inheritdoc
     */
    public function isStarted()
    {
        return $this->started || $this->pending;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __set($name, $value)
    {
        $this->startPending();

        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __unset($name)
    {
        // A session that has not started holds nothing to remove.
        if ($this->pending) {
            return;
        }

        parent::__unset($name);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __isset($name)
    {
        return !$this->pending && parent::__isset($name);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __get($name)
    {
        return $this->pending ? null : parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function getAll()
    {
        return $this->pending ? [] : parent::getAll();
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return $this->pending ? new \ArrayIterator([]) : parent::getIterator();
    }

    /**
     * @inheritdoc
     */
    public function regenerateId()
    {
        $this->startPending();

        return parent::regenerateId();
    }

    /**
     * @inheritdoc
     */
    public function invalidate()
    {
        if ($this->pending) {
            $this->pending = false;

            return $this;
        }

        return parent::invalidate();
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        if ($this->pending) {
            return $this;
        }

        return parent::close();
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        if ($this->pending) {
            return $this;
        }

        return parent::clear();
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
     * @deprecated 1.5 Use ->getAll() method instead.
     */
    public function all()
    {
        user_error(self::class . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use ->getAll() method instead', E_USER_DEPRECATED);

        return $this->getAll();
    }

    /**
     * Checks if the session was started.
     *
     * @return bool
     * @deprecated 1.5 Use ->isStarted() method instead.
     */
    public function started()
    {
        user_error(self::class . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use ->isStarted() method instead', E_USER_DEPRECATED);

        return $this->isStarted();
    }

    /**
     * Store something in session temporarily.
     *
     * @param string $name
     * @return $this
     */
    public function setFlashObject($name, mixed $object)
    {
        // GHSA-vj3m-2g9h-vm4p (#3): wrap the serialized payload with an HMAC so a
        // tampered session file can't smuggle in arbitrary class instantiation.
        $serialized = serialize($object);
        $hmac = hash_hmac('sha256', $serialized, Security::getNonceKey());
        $this->__set($name, "v2|{$hmac}|" . $serialized);

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
        $stored = $this->__get($name);

        $object = null;
        if (is_string($stored) && str_starts_with($stored, 'v2|')) {
            // 3-field format: v2|<hmac>|<serialized>. The serialized payload may
            // itself contain `|`, so split with limit=3.
            $parts = explode('|', $stored, 3);
            if (count($parts) === 3) {
                [, $expectedHmac, $serialized] = $parts;
                $actualHmac = hash_hmac('sha256', $serialized, Security::getNonceKey());
                if (hash_equals($expectedHmac, $actualHmac)) {
                    try {
                        $object = unserialize($serialized, ['allowed_classes' => true]);
                    } catch (\Throwable) {
                        $object = null;
                    }
                }
            }
        } elseif (!is_string($stored)) {
            $object = $stored;
        }
        // Legacy unsigned strings or HMAC mismatches fall through with $object = null.

        $this->__unset($name);

        if ($name === 'files-upload') {
            $grav = Grav::instance();

            // Make sure that Forms 3.0+ has been installed.
            if (null === $object && isset($grav['forms'])) {
//                user_error(
//                    __CLASS__ . '::' . __FUNCTION__ . '(\'files-upload\') is deprecated since Grav 1.6, use $form->getFlash()->getLegacyFiles() instead',
//                    E_USER_DEPRECATED
//                );

                /** @var Uri $uri */
                $uri = $grav['uri'];
                /** @var Forms|null $form */
                $form = $grav['forms']->getActiveForm(); // @phpstan-ignore-line (form plugin)

                $sessionField = base64_encode($uri->url);

                /** @var FormFlash|null $flash */
                $flash = $form ? $form->getFlash() : null; // @phpstan-ignore-line (form plugin)
                $object = $flash && method_exists($flash, 'getLegacyFiles') ? [$sessionField => $flash->getLegacyFiles()] : null;
            }
        }

        return $object;
    }

    /**
     * Store something in cookie temporarily.
     *
     * @param string $name
     * @param int $time
     * @return $this
     * @throws JsonException
     */
    public function setFlashCookieObject($name, mixed $object, $time = 60)
    {
        setcookie($name, json_encode($object, JSON_THROW_ON_ERROR), $this->getCookieOptions($time));

        return $this;
    }

    /**
     * Return object and remove it from the cookie.
     *
     * @param string $name
     * @return mixed|null
     * @throws JsonException
     */
    public function getFlashCookieObject($name)
    {
        if (isset($_COOKIE[$name])) {
            $cookie = $_COOKIE[$name];
            setcookie($name, '', $this->getCookieOptions(-42000));

            return json_decode((string) $cookie, false, 512, JSON_THROW_ON_ERROR);
        }

        return null;
    }

    /**
     * @return void
     */
    protected function onBeforeSessionStart(): void
    {
        $event = new BeforeSessionStartEvent($this);

        $grav = Grav::instance();
        $grav->dispatchEvent($event);
    }

    /**
     * @return void
     */
    protected function onSessionStart(): void
    {
        $event = new SessionStartEvent($this);

        $grav = Grav::instance();
        $grav->dispatchEvent($event);
    }
}
