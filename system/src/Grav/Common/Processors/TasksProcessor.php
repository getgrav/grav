<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class TasksProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'tasks';
    public $title = 'Tasks';

    public function process() {
        $task = $this->container['task'];
        if ($task) {
            $this->container->fireEvent('onTask.' . $task);
        }
    }

}
