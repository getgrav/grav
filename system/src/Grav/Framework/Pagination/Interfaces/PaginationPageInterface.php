<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination\Interfaces;

/**
 * Interface PaginationPageInterface
 * @package Grav\Framework\Pagination\Interfaces
 */
interface PaginationPageInterface
{
    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @return array
     */
    public function getOptions(): array;

    /**
     * @return int|null
     */
    public function getNumber(): ?int;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @return string|null
     */
    public function getUrl(): ?string;
}
