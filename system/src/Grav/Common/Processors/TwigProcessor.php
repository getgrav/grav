<?php
namespace Grav\Common\Processors;

class TwigProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'twig';
    public $title = 'Twig';

    public function process($debugger) {
      $this->container['twig']->init();
    }

}
