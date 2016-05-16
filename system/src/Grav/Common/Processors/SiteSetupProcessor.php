<?php
namespace Grav\Common\Processors;

class SiteSetupProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_setup';
    public $title = 'Site Setup';

    public function process() {
      	$this->container['setup']->init();
      	$this->container['streams'];
    }

}
