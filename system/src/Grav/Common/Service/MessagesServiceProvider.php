<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\Session\Message;

class MessagesServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        // Define session message service.
        $container['messages'] = function ($c) {
            $session = $c['session'];

            if (!isset($session->messages)) {
                $session->messages = new Message;
            }

            return $session->messages;
        };
    }
}
