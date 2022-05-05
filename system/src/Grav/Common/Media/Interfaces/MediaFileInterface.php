<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

/**
 * Class implements media file interface.
 */
interface MediaFileInterface extends MediaObjectInterface
{
    /**
     * Check if this medium exists or not
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * Get file modification time for the medium.
     *
     * @return int|null
     */
    public function modified(): ?int;

    /**
     * Get size of the medium.
     *
     * @return int
     */
    public function size(): int;

    /**
     * Return the path to file.
     *
     * @param bool $reset
     * @return string path to file
     */
    public function path(bool $reset = true): string;

    /**
     * Return the relative path to file
     *
     * @param bool $reset
     * @return string
     */
    public function relativePath(bool $reset = true): string;

    /**
     * @return string
     * @throws \RuntimeException
     */
    public function readFile(): string;

    /**
     * @return resource
     * @throws \RuntimeException
     */
    public function readStream();
}
