<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

use ArrayIterator;
use Grav\Framework\Pagination\Interfaces\PaginationInterface;
use Grav\Framework\Route\Route;
use function count;

/**
 * Class AbstractPagination
 * @package Grav\Framework\Pagination
 */
class AbstractPagination implements PaginationInterface
{
    /** @var Route Base rouse used for the pagination. */
    protected $route;
    /** @var int|null  Current page. */
    protected $page;
    /** @var int|null  The record number to start displaying from. */
    protected $start;
    /** @var int  Number of records to display per page. */
    protected $limit;
    /** @var int  Total number of records. */
    protected $total;
    /** @var array Pagination options */
    protected $options;
    /** @var bool View all flag. */
    protected $viewAll;
    /** @var int  Total number of pages. */
    protected $pages;
    /** @var int  Value pagination object begins at. */
    protected $pagesStart;
    /** @var int  Value pagination object ends at .*/
    protected $pagesStop;
    /** @var array */
    protected $defaultOptions = [
        'type' => 'page',
        'limit' => 10,
        'display' => 5,
        'opening' => 0,
        'ending' => 0,
        'url' => null,
        'param' => null,
        'use_query_param' => false
    ];
    /** @var array */
    private $items;

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->count() > 1;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return Route|null
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * @return int
     */
    public function getTotalPages(): int
    {
        return $this->pages;
    }

    /**
     * @return int
     */
    public function getPageNumber(): int
    {
        return $this->page ?? 1;
    }

    /**
     * @param int $count
     * @return int|null
     */
    public function getPrevNumber(int $count = 1): ?int
    {
        $page = $this->page - $count;

        return $page >= 1 ? $page : null;
    }

    /**
     * @param int $count
     * @return int|null
     */
    public function getNextNumber(int $count = 1): ?int
    {
        $page = $this->page + $count;

        return $page <= $this->pages ? $page : null;
    }

    /**
     * @param int $page
     * @param string|null $label
     * @return PaginationPage|null
     */
    public function getPage(int $page, string $label = null): ?PaginationPage
    {
        if ($page < 1 || $page > $this->pages) {
            return null;
        }

        $start = ($page - 1) * $this->limit;
        $type = $this->getOptions()['type'];
        $param = $this->getOptions()['param'];
        $useQuery = $this->getOptions()['use_query_param'];
        if ($type === 'page') {
            $param = $param ?? 'page';
            $offset = $page;
        } else {
            $param = $param ?? 'start';
            $offset = $start;
        }

        if ($useQuery) {
            $route = $this->route->withQueryParam($param, $offset);
        } else {
            $route = $this->route->withGravParam($param, $offset);
        }

        return new PaginationPage(
            [
                'label' => $label ?? (string)$page,
                'number' => $page,
                'offset_start' => $start,
                'offset_end' => min($start + $this->limit, $this->total) - 1,
                'enabled' => $page !== $this->page || $this->viewAll,
                'active' => $page === $this->page,
                'route' => $route
            ]
        );
    }

    /**
     * @param string|null $label
     * @param int $count
     * @return PaginationPage|null
     */
    public function getFirstPage(string $label = null, int $count = 0): ?PaginationPage
    {
        return $this->getPage(1 + $count, $label ?? $this->getOptions()['label_first'] ?? null);
    }

    /**
     * @param string|null $label
     * @param int $count
     * @return PaginationPage|null
     */
    public function getPrevPage(string $label = null, int $count = 1): ?PaginationPage
    {
        return $this->getPage($this->page - $count, $label ?? $this->getOptions()['label_prev'] ?? null);
    }

    /**
     * @param string|null $label
     * @param int $count
     * @return PaginationPage|null
     */
    public function getNextPage(string $label = null, int $count = 1): ?PaginationPage
    {
        return $this->getPage($this->page + $count, $label ?? $this->getOptions()['label_next'] ?? null);
    }

    /**
     * @param string|null $label
     * @param int $count
     * @return PaginationPage|null
     */
    public function getLastPage(string $label = null, int $count = 0): ?PaginationPage
    {
        return $this->getPage($this->pages - $count, $label ?? $this->getOptions()['label_last'] ?? null);
    }

    /**
     * @return int
     */
    public function getStart(): int
    {
        return $this->start ?? 0;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $this->loadItems();

        return count($this->items);
    }

    /**
     * @return ArrayIterator
     * @phpstan-return ArrayIterator<int,PaginationPage>
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        $this->loadItems();

        return new ArrayIterator($this->items);
    }

    /**
     * @return array
     */
    public function getPages(): array
    {
        $this->loadItems();

        return $this->items;
    }

    /**
     * @return void
     */
    protected function loadItems()
    {
        $this->calculateRange();

        // Make list like: 1 ... 4 5 6 ... 10
        $range = range($this->pagesStart, $this->pagesStop);
        //$range[] = 1;
        //$range[] = $this->pages;
        natsort($range);
        $range = array_unique($range);

        $this->items = [];
        foreach ($range as $i) {
            $this->items[$i] = $this->getPage($i);
        }
    }

    /**
     * @param Route $route
     * @return $this
     */
    protected function setRoute(Route $route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @param array|null $options
     * @return $this
     */
    protected function setOptions(array $options = null)
    {
        $this->options = $options ? array_merge($this->defaultOptions, $options) : $this->defaultOptions;

        return $this;
    }

    /**
     * @param int|null $page
     * @return $this
     */
    protected function setPage(int $page = null)
    {
        $this->page = (int)max($page, 1);
        $this->start = null;

        return $this;
    }

    /**
     * @param int|null $start
     * @return $this
     */
    protected function setStart(int $start = null)
    {
        $this->start = (int)max($start, 0);
        $this->page = null;

        return $this;
    }

    /**
     * @param int|null $limit
     * @return $this
     */
    protected function setLimit(int $limit = null)
    {
        $this->limit = (int)max($limit ?? $this->getOptions()['limit'], 0);

        // No limit, display all records in a single page.
        $this->viewAll = !$limit;

        return $this;
    }

    /**
     * @param int $total
     * @return $this
     */
    protected function setTotal(int $total)
    {
        $this->total = (int)max($total, 0);

        return $this;
    }

    /**
     * @param Route $route
     * @param int $total
     * @param int|null $pos
     * @param int|null $limit
     * @param array|null $options
     * @return void
     */
    protected function initialize(Route $route, int $total, int $pos = null, int $limit = null, array $options = null)
    {
        $this->setRoute($route);
        $this->setOptions($options);
        $this->setTotal($total);
        if ($this->getOptions()['type'] === 'start') {
            $this->setStart($pos);
        } else {
            $this->setPage($pos);
        }
        $this->setLimit($limit);
        $this->calculateLimits();
    }

    /**
     * @return void
     */
    protected function calculateLimits()
    {
        $limit = $this->limit;
        $total = $this->total;

        if (!$limit || $limit > $total) {
            // All records fit into a single page.
            $this->start = 0;
            $this->page = 1;
            $this->pages = 1;

            return;
        }

        if (null === $this->start) {
            // If we are using page, convert it to start.
            $this->start = (int)(($this->page - 1) * $limit);
        }

        if ($this->start > $total - $limit) {
            // If start is greater than total count (i.e. we are asked to display records that don't exist)
            // then set start to display the last natural page of results.
            $this->start = (int)max(0, (ceil($total / $limit) - 1) * $limit);
        }

        // Set the total pages and current page values.
        $this->page = (int)ceil(($this->start + 1) / $limit);
        $this->pages = (int)ceil($total / $limit);
    }

    /**
     * @return void
     */
    protected function calculateRange()
    {
        $options = $this->getOptions();
        $displayed = $options['display'];
        $opening = $options['opening'];
        $ending = $options['ending'];

        // Set the pagination iteration loop values.
        $this->pagesStart = $this->page - (int)($displayed / 2);
        if ($this->pagesStart < 1 + $opening) {
            $this->pagesStart = 1 + $opening;
        }
        if ($this->pagesStart + $displayed - $opening > $this->pages) {
            $this->pagesStop = $this->pages;
            if ($this->pages < $displayed) {
                $this->pagesStart = 1 + $opening;
            } else {
                $this->pagesStart = $this->pages - $displayed + 1 + $opening;
            }
        } else {
            $this->pagesStop = (int)max(1, $this->pagesStart + $displayed - 1 - $ending);
        }
    }
}
