<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Grav;

abstract class ProcessorBase
{
    /**
     * @var Grav
     */
    protected $container;

    public function __construct(Grav $container)
    {
        $this->container = $container;
    }

}
