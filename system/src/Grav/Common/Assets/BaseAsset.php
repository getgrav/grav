<?php
/**
 * @package    Grav.Common.Assets
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Assets;

use Grav\Framework\Object\PropertyObject;

abstract class BaseAsset extends PropertyObject
{
    protected $group;
    protected $position;
    protected $priority;

    abstract function render();
}
