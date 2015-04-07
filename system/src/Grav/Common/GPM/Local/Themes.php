<?php
namespace Grav\Common\GPM\Local;

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
        parent::__construct(self::getGrav()['themes']->all());
    }
}
