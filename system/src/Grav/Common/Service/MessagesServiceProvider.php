<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Debugger;
use Grav\Common\Session;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\Session\Message;

class MessagesServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        // Define session message service.
        $container['messages'] = function ($c) {
            if (!isset($c['session']) || !$c['session']->started()) {
                /** @var Debugger $debugger */
                $debugger = $c['debugger'];
                $debugger->addMessage('Session not started: session messages may disappear', 'warming');

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
