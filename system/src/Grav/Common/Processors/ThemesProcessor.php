<?php
namespace Grav\Common\Processors;

class ThemesProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'themes';
    public $title = 'Themes';

    public function process($debugger) {
      $this->container['themes']->init();
    }

}
