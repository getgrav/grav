<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

use Grav\Framework\Route\Route;

/**
 * Class Pagination
 * @package Grav\Framework\Pagination
 */
class Pagination extends AbstractPagination
{
    /**
     * Pagination constructor.
     * @param Route $route
     * @param int $total
     * @param int|null $pos
     * @param int|null $limit
     * @param array|null $options
     */
    public function __construct(Route $route, int $total, int $pos = null, int $limit = null, array $options = null)
    {
        $this->initialize($route, $total, $pos, $limit, $options);
    }
}
