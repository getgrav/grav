<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

/**
 * Class PaginationPage
 * @package Grav\Framework\Pagination
 */
class PaginationPage extends AbstractPaginationPage
{
    /**
     * PaginationPage constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }
}
