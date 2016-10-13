<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccess;

class Header implements \ArrayAccess
{
    use NestedArrayAccess, Constructor;
}
