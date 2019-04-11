<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\GPM\Common\CachedCollection;

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
