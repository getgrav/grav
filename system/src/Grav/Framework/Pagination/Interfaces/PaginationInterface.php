<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination\Interfaces;

use Grav\Framework\Pagination\PaginationPage;

interface PaginationInterface extends \Countable, \IteratorAggregate
{
    public function getTotalPages(): int;

    public function getPageNumber(): int;

    public function getPrevNumber(int $count = 1): ?int;

    public function getNextNumber(int $count = 1): ?int;

    public function getStart(): int;

    public function getLimit(): int;

    public function getTotal(): int;

    public function count(): int;

    public function getOptions(): array;

    public function getPage(int $page, string $label = null): ?PaginationPage;

    public function getFirstPage(string $label = null, int $count = 0): ?PaginationPage;

    public function getPrevPage(string $label = null, int $count = 1): ?PaginationPage;

    public function getNextPage(string $label = null, int $count = 1): ?PaginationPage;

    public function getLastPage(string $label = null, int $count = 0): ?PaginationPage;
}
