<?php
namespace Grav\Common;

class Theme extends Plugin
{
    public $name;

    /**
     * Constructor.
     *
     * @param Grav $grav
     * @param Config $config
     * @param string $name
     */
    public function __construct(Grav $grav, Config $config, $name)
    {
        $this->name = $name;

        parent::__construct($grav, $config);
    }
}
