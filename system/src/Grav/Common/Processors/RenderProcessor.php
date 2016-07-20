<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class RenderProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = 'render';
    public $title = 'Render';

    public function process() {
      	$this->container->output = $this->container['output'];
      	$this->container->fireEvent('onOutputGenerated');
    }

}
