<?php
namespace Grav\Common\GPM\Local;

class Plugins extends Collection
{
    private $type = 'plugins';
    public function __construct()
    {
        $grav = self::$grav;

        foreach ($grav['plugins']->all() as $name => $data) {
            $this->items[$name] = new Package($data, $this->type);
        }
    }
}
