<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Filesystem
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Filesystem\Interfaces;

interface FilesystemInterface
{
    /**
     * Returns parent path. Empty path is returned if there are no segments remaining.
     *
     * Can be used recursively to get towards the root directory.
     *
     * @param string    $path       A filename or path, does not need to exist as a file
     * @param int       $levels     The number of parent directories to go up (>= 1)
     * @return string
     * @throws \RuntimeException
     */
    public function parent(string $path, int $levels = 1): string;

    /**
     * Normalize path by cleaning up \ , /./ , // and /../
     *
     * @param string    $path       A filename or path, does not need to exist as a file
     * @return string
     * @throws \RuntimeException
     */
    public function normalize(string $path): string;

    /**
     * Stream-safe \dirname() replacement.
     *
     * @see   http://php.net/manual/en/function.dirname.php
     *
     * @param string    $path       A filename or path, does not need to exist as a file
     * @param int       $levels     The number of parent directories to go up (>= 1)
     * @return string
     * @throws \RuntimeException
     */
    public function dirname(string $path, int $levels = 1): string;

    /**
     * Stream-safe \pathinfo() replacement.
     *
     * @see   http://php.net/manual/en/function.pathinfo.php
     *
     * @param string    $path       A filename or path, does not need to exist as a file
     * @param int       $options    A PATHINFO_* constant
     *
     * @return array|string
     */
    public function pathinfo(string $path, int $options = null);
}
