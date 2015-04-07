<?php
namespace Grav\Common\GPM\Remote;

/**
 * Class Themes
 * @package Grav\Common\GPM\Remote
 */
class Themes extends AbstractPackageCollection
{
    /**
     * @var string
     */
    protected $type = 'themes';

    protected $repository = 'http://getgrav.org/downloads/themes.json';

    /**
     * Local Themes Constructor
     */
    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct($this->repository, $refresh, $callback);
    }
}
