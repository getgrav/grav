<?php
namespace Grav\Common\Processors;

class PluginsProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'plugins';
    public $title = 'Plugins';

    public function process() {
      	$this->container['plugins']->init();
      	$this->container->fireEvent('onPluginsInitialized');
    }

}
