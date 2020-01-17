<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;

class Header implements \ArrayAccess, ExportInterface, \JsonSerializable
{
    use NestedArrayAccessWithGetters, Constructor, Export;

    /** @var array */
    protected $items;

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
