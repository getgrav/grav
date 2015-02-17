<?php
namespace Grav\Common\GPM\Local;

/**
 * Class Plugins
 * @package Grav\Common\GPM\Local
 */
class Plugins extends Collection
{
    /**
     * @var string
     */
    private $type = 'plugins';

    /**
     * Local Plugins Constructor
     */
    public function __construct()
    {
        $grav = self::getGrav();

        foreach ($grav['plugins']->all() as $name => $data) {
            $this->items[$name] = new Package($data, $this->type);
        }
    }
}
