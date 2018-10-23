<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class DebuggerAssetsProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'debugger_assets';
    public $title = 'Debugger Assets';

    public function process()
    {
        $this->container['debugger']->addAssets();
    }
}
