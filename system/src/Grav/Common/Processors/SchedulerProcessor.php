<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class SchedulerProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = '_scheduler';
    public $title = 'Scheduler';

    public function process()
    {
        $this->container['scheduler']->loadSavedJobs();
        $this->container->fireEvent('onSchedulerInitialized');
    }
}
