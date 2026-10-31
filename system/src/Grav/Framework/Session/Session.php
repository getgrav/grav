<?php

/**
 * @package    Grav\Framework\Session
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Session;

use ArrayIterator;
use Exception;
use Throwable;
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
    /** @var bool Lock released after a read-only start; the first write re-acquires it. */
    protected $readonly = false;
    /** @var string|null Value only this site can produce, stored in every session it creates (see setSite()). */
    protected $site;
    /** @var int Until when a session stored before sessions were bound is still resumed (see setSite()). */
    protected $unboundUntil = 0;
    /** @var string|null Domain attribute of the session cookie as sent by older versions (see setLegacyCookieDomain()). */
    protected $legacyCookieDomain;

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
     * Bind the sessions this site creates to it.
     *
     * Session storage is usually shared: every site run by the same PHP stores its sessions in
     * the same session.save_path unless the server says otherwise, and the cookie name cannot
     * keep the sites apart because the client picks the name it sends an id under. With a site
     * value set, start() resumes only sessions carrying the same value and starts a new session
     * for any other id, leaving the session of the other site as it is.
     *
     * A session that carries no site value at all was stored before this site started binding
     * its sessions. Until `$unboundUntil` (a timestamp) such a session is resumed and bound, so
     * an update does not sign everyone out; after that it is refused like any other.
     *
     * @param string|null $site
     * @param int $unboundUntil
     * @return $this
     */
    public function setSite(?string $site, int $unboundUntil = 0)
    {
        $this->site = $site;
        $this->unboundUntil = $unboundUntil;

        return $this;
    }

    /**
     * Expire the session cookie which versions before 2.2.18 sent with `Domain=<host>`.
     *
     * A browser keeps such a cookie next to the one sent without the attribute, goes on sending
     * it to the hosts below this one and puts it first in the requests to this one, where PHP
     * takes it over the current cookie. When a domain is set here, every request which brings a
     * session cookie also gets that old variant removed.
     *
     * @param string|null $domain
     * @return $this
     */
    public function setLegacyCookieDomain(?string $domain)
    {
        $this->legacyCookieDomain = $domain;

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

        $sessionName = $this->getName();
        if (null === $sessionName) {
            return $this;
        }

        $sessionExists = isset($_COOKIE[$sessionName]);

        // Protection against invalid session cookie names throwing exception: http://php.net/manual/en/function.session-id.php#116836
        if ($sessionExists && !preg_match('/^[-,a-zA-Z0-9]{1,128}$/', (string) $_COOKIE[$sessionName])) {
            unset($_COOKIE[$sessionName]);
            $sessionExists = false;
        }

        $options = $this->options;

        try {
            $success = @session_start($options);
            if (!$success) {
                $last = error_get_last();
                $error = $last ? $last['message'] : 'Unknown error';

                throw new RuntimeException($error);
            }

            // The session was created by another site sharing the storage (or by this site before
            // it bound its sessions). Leave it untouched and start a new one with a new id.
            if (!$this->isSiteSession()) {
                session_abort();
                session_id(@session_create_id() ?: '');

                $success = @session_start($options);
                if (!$success) {
                    $last = error_get_last();
                    $error = $last ? $last['message'] : 'Unknown error';

                    throw new RuntimeException($error);
                }
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

        $this->bindToSite();

        $this->started = true;
        $this->onSessionStart();

        try {
            $user = $this->__get('user');
            if ($user && (!$user instanceof UserInterface || (method_exists($user, 'isValid') && !$user->isValid()))) {
                throw new RuntimeException('Bad user');
            }
        } catch (Throwable) {
            $this->invalidate();
            throw new SessionException('Invalid User object, session destroyed.', 500);
        }


        // Extend the lifetime of the session.
        if ($sessionExists) {
            $this->setCookie();
        }

        // Read-only start: now that the data has been read, validated and the
        // cookie refreshed, release the exclusive session lock immediately. The
        // session stays readable ($_SESSION is kept after close) and the first
        // write re-acquires the lock via reopen(). This lets requests that share
        // a session id (e.g. an SPA's parallel API calls, multi-tab browsing,
        // concurrent AJAX) read concurrently instead of serializing on PHP's
        // per-session file lock. Opt-in via system.session.read_and_close.
        if ($readonly && \PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
            $this->readonly = true;
        }

        return $this;
    }

    /**
     * Re-acquire the exclusive session lock after a read-only start so a write
     * can persist for the rest of the request. No-op unless the session was
     * started read-only and its lock has since been released. Re-opens with the
     * same id and without re-sending the cookie (already set during start()).
     *
     * PHP has no API to take the lock back without also re-reading the session, so
     * the session_start() below repopulates $_SESSION from storage. Whatever this
     * request already put there is kept and the fresh read laid underneath it, so a
     * write made before the reopen survives while a key a concurrent request added
     * in the meantime still comes through. Two requests writing the same key is the
     * one case the local value wins outright -- the read-modify-write tradeoff
     * read_and_close already documents.
     */
    protected function reopen(): void
    {
        if (!$this->readonly) {
            return;
        }

        $this->readonly = false;

        if (\PHP_SAPI === 'cli' || \PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        // Once the response headers are out, PHP refuses session_start() and
        // session_id() alike, so the lock cannot be taken back. That is the case
        // when something finished the request before Grav's own shutdown, such as a
        // plugin calling fastcgi_finish_request() from its own shutdown function.
        // The warning session_id() raised there became an exception that aborted
        // Grav::shutdown() before onShutdown. Keep this request's data in memory,
        // as for a session that is gone.
        if (headers_sent()) {
            return;
        }

        $options = $this->options;
        $options['use_cookies'] = '0';

        $current = $_SESSION;
        $id = session_id();

        $success = @session_start($options);

        // The session can be gone by now: a concurrent request destroyed it, or gc
        // collected it. With use_strict_mode on, PHP answers that by minting a new
        // id, and because no cookie is sent the browser never learns about it, so
        // every later write would land in a session nobody can read back. Give that
        // one up and keep this request's data in memory instead.
        if (!$success || session_id() !== $id) {
            if (\PHP_SESSION_ACTIVE === session_status()) {
                session_abort();
            }
            session_id($id);
            $_SESSION = $current;

            return;
        }

        $_SESSION = $current + $_SESSION;
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
        // Rotating the id writes — re-acquire the lock if it was released after
        // a read-only start, otherwise the session would look unstarted here.
        $this->reopen();

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

            $this->onBeforeSessionStart();

            $success = @session_start($this->options);
            if (!$success) {
                $last = error_get_last();
                $error = $last ? $last['message'] : 'Unknown error';

                throw new RuntimeException($error);
            }

            $this->bindToSite();

            $this->onSessionStart();
        }

        // New session does not have these.
        $this->__unset('session_destroyed');
        $this->__unset('session_new_id');

        // PHP drops every Set-Cookie header of the session name when it sends the new id, the
        // removal of the legacy cookie among them: send it again.
        if (null !== $this->legacyCookieDomain) {
            $this->setCookie();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function invalidate()
    {
        // Destroying the session writes — re-acquire the lock if it was released.
        $this->reopen();

        $name = $this->getName();
        if (null !== $name) {
            $this->removeCookie();
            $this->removeLegacyCookie();

            setcookie(
                $name,
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
        // After a read-only start the lock is released and the session closed, but
        // this request may still have changed something $_SESSION holds by reference
        // -- a flash message added to the Messages object, a mutated user -- which no
        // __set() ever announced. Take the lock back one last time so those land.
        $this->reopen();

        if ($this->started && \PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        $this->started = false;
        $this->readonly = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->reopen();
        session_unset();
        $this->bindToSite();

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
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
    public function __isset($name)
    {
        return isset($_SESSION[$name]);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __get($name)
    {
        return $_SESSION[$name] ?? null;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __set($name, $value)
    {
        $this->reopen();
        $_SESSION[$name] = $value;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function __unset($name)
    {
        $this->reopen();
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
     * Was the current session created by this site? Every session this site creates carries its
     * value (see bindToSite()), so a session without it, or with another site's, is not resumed.
     * An empty session holds nothing to take over: it is as good as the new one it would be
     * replaced with, and that is what a session which has just been created looks like.
     *
     * @return bool
     */
    protected function isSiteSession(): bool
    {
        if (null === $this->site || !$_SESSION) {
            return true;
        }

        if (!array_key_exists('session_site', $_SESSION)) {
            return time() < $this->unboundUntil;
        }

        $site = $_SESSION['session_site'];

        return is_string($site) && hash_equals($this->site, $site);
    }

    /**
     * Mark the current session as created by this site.
     *
     * @return void
     */
    protected function bindToSite(): void
    {
        if (null !== $this->site) {
            $_SESSION['session_site'] = $this->site;
        }
    }

    protected function onBeforeSessionStart(): void
    {
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
    public function getCookieOptions(?int $lifetime = null): array
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

        $sessionName = $this->getName();
        $sessionId = $this->getId();
        if (null === $sessionName || null === $sessionId) {
            return;
        }

        $this->removeLegacyCookie();

        setcookie(
            $sessionName,
            $sessionId,
            $this->getCookieOptions()
        );
    }

    /**
     * Expire the legacy cookie of the session name (see setLegacyCookieDomain()) if the request brought a session cookie.
     *
     * Always sent before the current cookie, as removeCookie() strips every header of the session name.
     *
     * @return void
     */
    protected function removeLegacyCookie(): void
    {
        $sessionName = $this->getName();
        if (null === $this->legacyCookieDomain || null === $sessionName || !isset($_COOKIE[$sessionName])) {
            return;
        }

        setcookie(
            $sessionName,
            '',
            ['domain' => $this->legacyCookieDomain] + $this->getCookieOptions(-42000)
        );
    }

    protected function removeCookie(): void
    {
        $search = " {$this->getName()}=";
        $cookies = [];
        $found = false;

        foreach (headers_list() as $header) {
            // Identify cookie headers
            if (str_starts_with($header, 'Set-Cookie:')) {
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
     * @return void
     */
    protected function setOption($key, mixed $value)
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
