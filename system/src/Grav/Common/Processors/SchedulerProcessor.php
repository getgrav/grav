<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use RocketTheme\Toolbox\Event\Event;

class SchedulerProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = '_scheduler';
    public $title = 'Scheduler';

    public function process()
    {
        $scheduler = $this->container['scheduler'];
        $scheduler->loadSavedJobs();
        $this->container->fireEvent('onSchedulerInitialized', new Event(['scheduler' => $scheduler]));
    }
}
