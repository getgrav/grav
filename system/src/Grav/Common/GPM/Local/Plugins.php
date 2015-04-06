<?php
namespace Grav\Common\GPM\Local;

/**
 * Class Plugins
 * @package Grav\Common\GPM\Local
 */
class Plugins extends AbstractPackageCollection
{
    /**
     * @var string
     */
    protected $type = 'plugins';

    /**
     * Local Plugins Constructor
     */
    public function __construct()
    {
        parent::__construct(self::getGrav()['plugins']->all());
    }
}
