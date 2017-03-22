<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class PluginsProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'plugins';
    public $title = 'Plugins';

    public function process()
    {
        $this->container['plugins']->init();
        $this->container->fireEvent('onPluginsInitialized');
    }
}
