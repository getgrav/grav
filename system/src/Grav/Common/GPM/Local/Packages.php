<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\GPM\Common\CachedCollection;

/**
 * Class Packages
 * @package Grav\Common\GPM\Local
 */
class Packages extends CachedCollection
{
    public function __construct()
    {
        $items = [
            'plugins' => new Plugins(),
            'themes' => new Themes()
        ];

        parent::__construct($items);
    }
}
