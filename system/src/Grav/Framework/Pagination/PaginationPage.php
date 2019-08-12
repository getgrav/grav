<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

class PaginationPage extends AbstractPaginationPage
{
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }
}
