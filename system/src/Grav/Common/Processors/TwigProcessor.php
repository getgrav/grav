<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class TwigProcessor extends ProcessorBase implements ProcessorInterface
{
    public $id = 'twig';
    public $title = 'Twig';

    public function process()
    {
        $this->container['twig']->init();
    }

}
