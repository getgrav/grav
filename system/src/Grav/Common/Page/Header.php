<?php

namespace Grav\Common\Page;

use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccess;

/**
 * Class Header
 * @package Grav\Common\Page
 */
class Header implements \ArrayAccess
{
    use NestedArrayAccess, Constructor;
}
