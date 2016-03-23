<?php
namespace Grav\Common\Processors;

class TasksProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'tasks';
    public $title = 'Tasks';

    public function process($debugger) {
      $task = $this->container['task'];
      if ($task) {
          $this->container->fireEvent('onTask.' . $task);
      }
    }

}
