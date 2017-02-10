<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

use Grav\Common\Grav;

class ProcessorBase
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
