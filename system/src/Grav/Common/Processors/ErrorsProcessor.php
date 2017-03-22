<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class ErrorsProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = '_errors';
    public $title = 'Error Handlers Reset';

    public function process()
    {
        $this->container['errors']->resetHandlers();
    }
}
