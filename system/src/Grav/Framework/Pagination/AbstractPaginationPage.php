<?php

/**
 * @package    Grav\Framework\Pagination
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Pagination;

use Grav\Framework\Pagination\Interfaces\PaginationPageInterface;

/**
 * Class AbstractPaginationPage
 * @package Grav\Framework\Pagination
 */
abstract class AbstractPaginationPage implements PaginationPageInterface
{
    /** @var array */
    protected $options;

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->options['active'] ?? false;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->options['enabled'] ?? false;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return int|null
     */
    public function getNumber(): ?int
    {
        return $this->options['number'] ?? null;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->options['label'] ?? (string)$this->getNumber();
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->options['route'] ? (string)$this->options['route']->getUri() : null;
    }

    /**
     * @param array $options
     */
    protected function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
