<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use RocketTheme\Toolbox\Event\Event;

class BackupsProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = '_backups';
    public $title = 'Backups';

    public function process()
    {
        $backups = $this->container['backups'];
        $backups->init();
    }
}
