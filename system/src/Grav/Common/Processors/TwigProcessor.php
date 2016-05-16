<?php
namespace Grav\Common\Processors;

class TwigProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'twig';
    public $title = 'Twig';

    public function process() {
      	$this->container['twig']->init();
    }

}
