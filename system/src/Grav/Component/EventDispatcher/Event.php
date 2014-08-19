<?php
namespace Grav\Component\EventDispatcher;

use Grav\Component\ArrayTraits\ArrayAccess;
use Grav\Component\ArrayTraits\Constructor;

class Event extends \Symfony\Component\EventDispatcher\Event implements \ArrayAccess
{
    use ArrayAccess, Constructor;

    /**
     * @var array
     */
    protected $items = array();
}
