<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\Grav;

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
        /** @var \Grav\Common\Plugins $plugins */
        $plugins = Grav::instance()['plugins'];
        parent::__construct($plugins->all());
    }
}
