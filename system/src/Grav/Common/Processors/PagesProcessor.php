<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

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
