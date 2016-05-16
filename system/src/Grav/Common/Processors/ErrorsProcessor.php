<?php
namespace Grav\Common\Processors;

class ErrorsProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_errors';
    public $title = 'Error Handlers Reset';

    public function process() {
      	$this->container['errors']->resetHandlers();
    }

}
