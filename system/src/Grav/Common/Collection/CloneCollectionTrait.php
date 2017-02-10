<?php
namespace Grav\Common\Collection;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;

trait CloneCollectionTrait
{
    use ArrayAccess;

    public function __clone()
    {
        foreach ($this as $key => $value) {
            if (is_object($value)) {
                $this->offsetSet($key, clone $value);
            }
        }
    }

    /**
     *
     * Create a clone from this collection.
     *
     * @return static
     */
    public function getClone()
    {
        return clone $this;
    }
}
