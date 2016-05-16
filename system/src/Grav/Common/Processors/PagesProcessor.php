<?php
namespace Grav\Common\Processors;

class PagesProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'pages';
    public $title = 'Pages';

    public function process() {
      	$this->container['pages']->init();
      	$this->container->fireEvent('onPagesInitialized');
      	$this->container->fireEvent('onPageInitialized');
    }

}
