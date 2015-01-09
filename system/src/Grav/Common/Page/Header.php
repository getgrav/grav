<?php

namespace Grav\Common\Page;

use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccess;

class Header implements \ArrayAccess
{
    use NestedArrayAccess, Constructor;
}
