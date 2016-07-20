<?php
/**
 * @package    Grav.Common.GPM
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\Grav;

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
