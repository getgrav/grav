<?php
namespace Grav\Common\Collection;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Countable;

class AbstractCollection implements CollectionInterface
{
    use ArrayAccess, Countable;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @param array $variables
     * @return static
     */
    public static function __set_state(array $variables)
    {
        $instance = new static();
        $instance->items = $variables['items'];

        return $instance;
    }

    /**
     * Add item to the list.
     *
     * @param mixed $item
     * @param string $key
     * @return $this
     */
    public function add($item, $key = null)
    {
        $this->offsetSet($key, $item);

        return $this;
    }

    /**
     * Remove item from the list.
     *
     * @param $key
     */
    public function remove($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }
}
