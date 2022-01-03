<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Interfaces;

/**
 * Interface PagesSourceInterface
 * @package Grav\Common\Page\Interfaces
 */
interface PagesSourceInterface // extends \Iterator
{
    /**
     * Get timestamp for the page source.
     *
     * @return int
     */
    public function getTimestamp(): int;

    /**
     * Get checksum for the page source.
     *
     * @return string
     */
    public function getChecksum(): string;

    /**
     * Returns true if the source contains a page for the given route.
     *
     * @param string $route
     * @return bool
     */
    public function has(string $route): bool;

    /**
     * Get the page for the given route.
     *
     * @param string $route
     * @return PageInterface|null
     */
    public function get(string $route): ?PageInterface;

    /**
     * Get the children for the given route.
     *
     * @param string $route
     * @param array|null $options
     * @return array
     */
    public function getChildren(string $route, array $options = null): array;
}
