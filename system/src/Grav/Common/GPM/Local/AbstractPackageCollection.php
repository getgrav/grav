<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Local;

use Grav\Common\GPM\Common\AbstractPackageCollection as BaseCollection;

abstract class AbstractPackageCollection extends BaseCollection
{
    public function __construct($items)
    {
        parent::__construct();

        foreach ($items as $name => $data) {
            $data->set('slug', $name);
            $this->items[$name] = new Package($data, $this->type);
        }
    }
}
