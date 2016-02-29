<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\Grav;

/**
 * Class Themes
 * @package Grav\Common\GPM\Local
 */
class Themes extends AbstractPackageCollection
{
    /**
     * @var string
     */
    protected $type = 'themes';

    /**
     * Local Themes Constructor
     */
    public function __construct()
    {
        parent::__construct(Grav::instance()['themes']->all());
    }
}
