<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class ThemesProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'themes';
    public $title = 'Themes';

    public function process()
    {
        $this->container['themes']->init();
    }
}
