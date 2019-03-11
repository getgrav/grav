<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

use Grav\Framework\Pagination\Interfaces\PaginationPageInterface;

abstract class AbstractPaginationPage implements PaginationPageInterface
{
    /** @var array */
    protected $options;

    public function isActive(): bool
    {
        return $this->options['active'] ?? false;
    }

    public function isEnabled(): bool
    {
        return $this->options['enabled'] ?? false;
    }

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function getNumber(): ?int
    {
        return $this->options['number'] ?? null;
    }

    public function getLabel(): string
    {
        return $this->options['label'] ?? (string)$this->getNumber();
    }

    public function getUrl(): ?string
    {
        return $this->options['route'] ? (string)$this->options['route']->getUri() : null;
    }

    protected function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
