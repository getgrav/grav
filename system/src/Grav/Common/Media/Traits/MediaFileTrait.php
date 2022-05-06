<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Trait MediaFileTrait
 * @package Grav\Common\Media\Traits
 */
trait MediaFileTrait
{
    /**
     * Check if this medium exists or not
     *
     * @return bool
     * @phpstan-pure
     */
    public function exists(): bool
    {
        $path = $this->path(false);

        return file_exists($path);
    }

    /**
     * Get file modification time for the medium.
     *
     * @return int|null
     * @phpstan-pure
     */
    public function modified(): ?int
    {
        return $this->get('modified');
    }

    /**
     * Get size of the medium.
     *
     * Returns 0 if file does not exist or size is unknown.
     *
     * @return int
     * @phpstan-pure
     */
    public function size(): int
    {
        return $this->get('size');
    }

    /**
     * Return PATH to file.
     *
     * @param bool $reset
     * @return string path to file
     * @phpstan-impure
     */
    public function path(bool $reset = true): string
    {
        if ($reset) {
            $this->reset();
        }

        return $this->get('url') ?? $this->get('filepath');
    }

    /**
     * Return the relative path to file
     *
     * @param bool $reset
     * @return string
     * @phpstan-impure
     */
    public function relativePath(bool $reset = true): string
    {
        if ($reset) {
            $this->reset();
        }

        $path = $this->path(false);
        $output = preg_replace('|^' . preg_quote(GRAV_WEBROOT, '|') . '|', '', $path) ?: $path;

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        if ($locator->isStream($output)) {
            $output = (string)($locator->findResource($output, false) ?: $locator->findResource($output, false, true));
        }

        return $output;
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     * @phpstan-impure
     */
    public function url(bool $reset = true): string
    {
        /** @var string|null $url */
        $url = $this->get('url');
        if ($url) {
            return $url;
        }

        $path = $this->relativePath($reset);

        return trim($this->getGrav()['base_url'] . '/' . $this->urlQuerystring($path), '\\');
    }
}
