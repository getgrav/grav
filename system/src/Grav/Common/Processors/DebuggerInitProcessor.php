<?php
namespace Grav\Common\Processors;

class DebuggerInitProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_debugger';
    public $title = 'Init Debugger';

    public function process() {
      	$this->container['debugger']->init();
    }

}
