<?php
namespace Grav\Common\Processors;

class ConfigurationProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_config';
    public $title = 'Configuration';

    public function process() {
      	$this->container['config']->init();
      	return $this->container['plugins']->setup();
    }

}
