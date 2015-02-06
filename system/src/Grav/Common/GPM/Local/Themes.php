<?php
namespace Grav\Common\GPM\Local;

class Themes extends Collection
{
    private $type = 'themes';
    public function __construct()
    {
        $grav = self::getGrav();

        foreach ($grav['themes']->all() as $name => $data) {
            $this->items[$name] = new Package($data, $this->type);
        }
    }
}
