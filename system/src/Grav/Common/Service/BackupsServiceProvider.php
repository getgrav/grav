<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Backup\Backups;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class BackupsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['backups'] = function () {
            $backups = new Backups();
            $backups->setup();

            return $backups;
        };
    }
}
