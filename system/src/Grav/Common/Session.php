<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use RocketTheme\Toolbox\Session\Session as BaseSession;

class Session extends BaseSession
{
    protected $grav;
    protected $session;

    /**
     * Session constructor.
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /**
     * Session init
     */
    public function init()
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $config = $this->grav['config'];

        $is_admin = false;
        $base_url = $uri->rootUrl(false);

        $session_timeout = $config->get('system.session.timeout', 1800);
        $session_path = $config->get('system.session.path');
        if (!$session_path) {
            $session_path = '/' . ltrim($base_url, '/');
        }

        // Activate admin if we're inside the admin path.
        if ($config->get('plugins.admin.enabled')) {
            $route = $config->get('plugins.admin.route');
            // Uri::route() is not processed yet, let's quickly get what we need
            $current_route = str_replace($base_url, '', parse_url($uri->url(true), PHP_URL_PATH));
            $base = '/' . trim($route, '/');

            if (substr($current_route, 0, strlen($base)) == $base || //handle no language specified
                substr($current_route, 3, strlen($base)) == $base || //handle language (en)
                substr($current_route, 6, strlen($base)) == $base) { //handle region specific language prefix (en-US)
                $session_timeout = $config->get('plugins.admin.session.timeout', 1800);
                $is_admin = true;
            }
        }

        if ($config->get('system.session.enabled') || $is_admin) {
            $domain = $uri->host();
            if ($domain === 'localhost') {
                $domain = '';
            }

            // Fix for HUGE session timeouts
            if ($session_timeout > 99999999999) {
                $session_timeout = 9999999999;
            }

            // Define session service.
            parent::__construct($session_timeout, $session_path, $domain);

            $secure = $config->get('system.session.secure', false);
            $httponly = $config->get('system.session.httponly', true);

            $unique_identifier = GRAV_ROOT;
            $inflector = new Inflector();
            $session_name = $inflector->hyphenize($config->get('system.session.name', 'grav_site')) . '-' . substr(md5($unique_identifier), 0, 7);
            $split_session = $config->get('system.session.split', true);
            if ($is_admin && $split_session) {
              $session_name .= '-admin';
            }
            $this->setName($session_name);
            ini_set('session.cookie_secure', $secure);
            ini_set('session.cookie_httponly', $httponly);
            $this->start();
            setcookie(session_name(), session_id(), $session_timeout ? time() + $session_timeout : 0, $session_path, $domain, $secure, $httponly);
        }
    }

    // Store something in session temporarily
    public function setFlashObject($name, $object)
    {
        $this->$name = serialize($object);
    }

    // Return object and remove it from session
    public function getFlashObject($name)
    {
        $object = unserialize($this->$name);

        $this->$name = null;

        return $object;
    }

    // Store something in cookie temporarily
    public function setFlashCookieObject($name, $object, $time = 60)
    {
        setcookie($name, json_encode($object), time() + $time, '/');
    }

    // Return object and remove it from the cookie
    public function getFlashCookieObject($name)
    {
        if (isset($_COOKIE[$name])) {
            $object = json_decode($_COOKIE[$name]);
            setcookie($name, '', time() - 3600, '/');
            return $object;
        }
    }
}
