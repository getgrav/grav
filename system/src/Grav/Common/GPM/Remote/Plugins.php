<?php
namespace Grav\Common\GPM\Remote;

/**
 * Class Plugins
 * @package Grav\Common\GPM\Remote
 */
class Plugins extends AbstractPackageCollection
{
    /**
     * @var string
     */
    protected $type = 'plugins';

    protected $repository = 'http://getgrav.org/downloads/plugins.json';

    /**
     * Local Plugins Constructor
     */
    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct($this->repository, $refresh, $callback);
    }
}
