<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

use Grav\Framework\Route\Route;

class Pagination extends AbstractPagination
{
    public function __construct(Route $route, int $total, int $pos = null, int $limit = null, array $options = null)
    {
        $this->initialize($route, $total, $pos, $limit, $options);
    }
}
