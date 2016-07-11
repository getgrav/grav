<?php
/**
 * @package    Grav.Common.GPM
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\Grav;

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
