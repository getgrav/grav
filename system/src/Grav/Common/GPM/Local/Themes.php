<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\Grav;

/**
 * Class Themes
 * @package Grav\Common\GPM\Local
 */
class Themes extends AbstractPackageCollection
{
    /** @var string */
    protected $type = 'themes';

    /**
     * Local Themes Constructor
     */
    public function __construct()
    {
        /** @var \Grav\Common\Themes $themes */
        $themes = Grav::instance()['themes'];

        parent::__construct($themes->all());
    }
}
