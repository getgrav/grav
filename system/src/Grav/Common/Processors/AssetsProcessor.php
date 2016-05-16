<?php
namespace Grav\Common\Processors;

class AssetsProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'assets';
    public $title = 'Assets';

    public function process() {
		$this->container['assets']->init();
		$this->container->fireEvent('onAssetsInitialized');
    }

}
