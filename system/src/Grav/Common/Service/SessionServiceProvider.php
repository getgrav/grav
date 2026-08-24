<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Inflector;
use Grav\Common\Security;
use Grav\Common\Session;
use Grav\Common\Uri;
use Grav\Common\Utils;
use Grav\Framework\Session\Messages;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class SessionServiceProvider
 * @package Grav\Common\Service
 */
class SessionServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        // Define session service.
        $container['session'] = static function ($c) {
            /** @var Config $config */
            $config = $c['config'];

            /** @var Uri $uri */
            $uri = $c['uri'];

            // Get session options.
            $enabled = (bool)$config->get('system.session.enabled', false);
            $cookie_secure = $config->get('system.session.secure', false)
                || ($config->get('system.session.secure_https', true) && $uri->scheme(true) === 'https');
            $cookie_httponly = (bool)$config->get('system.session.httponly', true);
            $cookie_lifetime = (int)$config->get('system.session.timeout', 1800);
            $cookie_domain = $config->get('system.session.domain');
            $cookie_path = $config->get('system.session.path');
            $cookie_samesite = $config->get('system.session.samesite', 'Lax');

            if (null === $cookie_domain) {
                $cookie_domain = $uri->host();
                if ($cookie_domain === 'localhost') {
                    $cookie_domain = '';
                }
            }

            if (null === $cookie_path) {
                $cookie_path = '/' . trim(Uri::filterPath($uri->rootUrl(false)), '/');
            }
            // Session cookie path requires trailing slash.
            $cookie_path = rtrim($cookie_path, '/') . '/';

            // Activate admin if we're inside the admin path.
            $is_admin = false;
            if ($config->get('plugins.admin.enabled')) {
                $admin_base = '/' . trim((string) $config->get('plugins.admin.route'), '/');

                // Uri::route() is not processed yet, let's quickly get what we need.
                $current_route = str_replace(Uri::filterPath($uri->rootUrl(false)), '', parse_url($uri->url(true), PHP_URL_PATH));

                // Test to see if path starts with a supported language + admin base
                $lang = Utils::pathPrefixedByLangCode($current_route);
                $lang_admin_base = '/' . $lang . $admin_base;

                // Check no language, simple language prefix (en) and region specific language prefix (en-US).
                if (Utils::startsWith($current_route, $admin_base) || Utils::startsWith($current_route, $lang_admin_base)) {
                    $cookie_lifetime = $config->get('plugins.admin.session.timeout', 1800);
                    $enabled = $is_admin = true;
                }
            }

            // Fix for HUGE session timeouts.
            if ($cookie_lifetime > 99999999999) {
                $cookie_lifetime = 9999999999;
            }

            $session_prefix = static::resolveSessionPrefix($c['inflector'], (string) $config->get('system.session.name', 'grav-site'));

            // A browser applies the `__Secure-`/`__Host-` protections only when the cookie
            // also carries the attributes the prefix promises, and *discards the cookie
            // outright* when it does not. Preserving the name without lining the attributes
            // up would hand the site owner a silent login loop rather than the protection
            // they asked for -- and on Grav's shipped config it would, since `domain:` is
            // empty, which resolves to the request host below and is exactly what `__Host-`
            // forbids.
            if (str_starts_with($session_prefix, '__Secure-') || str_starts_with($session_prefix, '__Host-')) {
                $cookie_secure = true;
            }
            if (str_starts_with($session_prefix, '__Host-')) {
                // `__Host-` additionally forbids a Domain attribute and demands Path=/.
                $cookie_domain = '';
                $cookie_path = '/';
            }

            $session_uniqueness = $config->get('system.session.uniqueness', 'path') === 'path' ?  substr(md5(GRAV_ROOT), 0, 7) :  md5(Security::getNonceKey());

            $session_name = $session_prefix . '-' . $session_uniqueness;

            if ($is_admin && $config->get('system.session.split', true)) {
                $session_name .= '-admin';
            }

            // Define session service.
            $options = [
                'name' => $session_name,
                'cookie_lifetime' => $cookie_lifetime,
                'cookie_path' => $cookie_path,
                'cookie_domain' => $cookie_domain,
                'cookie_secure' => $cookie_secure,
                'cookie_httponly' => $cookie_httponly,
                'cookie_samesite' => $cookie_samesite
            ] + (array) $config->get('system.session.options');

            $session = new Session($options);
            $session->setAutoStart($enabled);

            return $session;
        };

        // Define session message service.
        $container['messages'] = function ($c) {
            if (!isset($c['session']) || !$c['session']->isStarted()) {
                /** @var Debugger $debugger */
                $debugger = $c['debugger'];
                $debugger->addMessage('Inactive session: session messages may disappear', 'warming');

                return new Messages();
            }

            /** @var Session $session */
            $session = $c['session'];

            if (!$session->messages instanceof Messages) {
                $session->messages = new Messages();
            }

            return $session->messages;
        };
    }

    /**
     * Resolves the configured session name into a session cookie name prefix.
     *
     * If the configured name is already a valid cookie name (RFC 6265 / RFC 2616 token), it is
     * used as-is. This preserves cookie name prefixes such as `__Secure-` and `__Host-`, which
     * browsers only honor on exact, case-sensitive, unmodified cookie names. Otherwise the name
     * is hyphenized to strip out invalid characters.
     *
     * @param Inflector $inflector
     * @param string $name
     * @return string
     */
    public static function resolveSessionPrefix(Inflector $inflector, string $name): string
    {
        // Keep a recognised cookie-name prefix verbatim and resolve only the remainder.
        // Treating the whole string as one token would drop the prefix entirely the
        // moment the rest of the name needed cleaning up -- `__Secure-My Site` would
        // come out as `secure-my-site`, losing the very thing this is here to preserve.
        foreach (['__Secure-', '__Host-'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $rest = substr($name, strlen($prefix));

                return $prefix . (static::isValidCookieName($rest) ? $rest : $inflector->hyphenize($rest));
            }
        }

        return static::isValidCookieName($name) ? $name : $inflector->hyphenize($name);
    }

    /**
     * Is this already usable verbatim as a cookie name?
     *
     * An RFC 6265 / RFC 2616 token, minus `.`. The dot is excluded deliberately: PHP
     * mangles dots in request-variable names, so a session cookie named `my.site`
     * reaches PHP as `my_site` and `Session::start()` -- which resumes via
     * `isset($_COOKIE[$sessionName])` -- would never find it again, handing out a
     * fresh session on every single request. `system.yaml` has always warned against
     * dots here for exactly that reason; hyphenizing them keeps that protection.
     *
     * `\A`/`\z` rather than `^`/`$`, because `$` also matches before a trailing
     * newline and would let `"grav-site\n"` through to `session_name()`.
     *
     * @param string $name
     * @return bool
     */
    private static function isValidCookieName(string $name): bool
    {
        return $name !== '' && (bool) preg_match('/\A[!#$%&\'*+\-^_`|~0-9A-Za-z]+\z/', $name);
    }
}
