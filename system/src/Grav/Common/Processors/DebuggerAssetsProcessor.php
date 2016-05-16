<?php
namespace Grav\Common\Processors;

class DebuggerAssetsProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'debugger_assets';
    public $title = 'Debugger Assets';

    public function process() {
      	$this->container['debugger']->addAssets();
    }

}
