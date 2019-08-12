<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination\Interfaces;

interface PaginationPageInterface
{
    public function isActive(): bool;

    public function isEnabled(): bool;

    public function getOptions(): array;

    public function getNumber(): ?int;

    public function getLabel(): string;

    public function getUrl(): ?string;
}
