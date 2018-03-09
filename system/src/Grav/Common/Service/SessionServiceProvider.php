<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Inflector;
use Grav\Common\Session;
use Grav\Common\Uri;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\Session\Message;

class SessionServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        // Define session service.
        $container['session'] = function ($c) {
            /** @var Config $config */
            $config = $c['config'];

            /** @var Uri $uri */
            $uri = $c['uri'];

            // Get session parameters.
            $session_timeout = (int)$config->get('system.session.timeout', 1800);
            $session_path = $config->get('system.session.path');
            if (null === $session_path) {
                $session_path = '/' . ltrim(Uri::filterPath($uri->rootUrl(false)), '/');
            }
            $domain = $uri->host();
            if ($domain === 'localhost') {
                $domain = '';
            }

            // Get session options.
            $secure = (bool)$config->get('system.session.secure', false);
            $httponly = (bool)$config->get('system.session.httponly', true);
            $enabled = (bool)$config->get('system.session.enabled', false);

            // Activate admin if we're inside the admin path.
            $is_admin = false;
            if ($config->get('plugins.admin.enabled')) {
                $base = '/' . trim($config->get('plugins.admin.route'), '/');

                // Uri::route() is not processed yet, let's quickly get what we need.
                $current_route = str_replace(Uri::filterPath($uri->rootUrl(false)), '', parse_url($uri->url(true), PHP_URL_PATH));

                // Check no language, simple language prefix (en) and region specific language prefix (en-US).
                $pos = strpos($current_route, $base);
                if ($pos === 0 || $pos === 3 || $pos === 6) {
                    $session_timeout = $config->get('plugins.admin.session.timeout', 1800);
                    $enabled = $is_admin = true;
                }
            }

            // Fix for HUGE session timeouts.
            if ($session_timeout > 99999999999) {
                $session_timeout = 9999999999;
            }

            $inflector = new Inflector();
            $session_name = $inflector->hyphenize($config->get('system.session.name', 'grav_site')) . '-' . substr(md5(GRAV_ROOT), 0, 7);
            if ($is_admin && $config->get('system.session.split', true)) {
                $session_name .= '-admin';
            }

            // Define session service.
            $session = new Session($session_timeout, $session_path, $domain);
            $session->setName($session_name);
            $session->setSecure($secure);
            $session->setHttpOnly($httponly);
            $session->setAutoStart($enabled);

            return $session;
        };

        // Define session message service.
        $container['messages'] = function ($c) {
            if (!isset($c['session']) || !$c['session']->started()) {
                /** @var Debugger $debugger */
                $debugger = $c['debugger'];
                $debugger->addMessage('Inactive session: session messages may disappear', 'warming');

                return new Message;
            }

            /** @var Session $session */
            $session = $c['session'];

            if (!isset($session->messages)) {
                $session->messages = new Message;
            }

            return $session->messages;
        };
    }
}
