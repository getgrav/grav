<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Filesystem
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Filesystem;

class Filesystem
{
    /** @var bool|null */
    private $normalizeNext;

    /**
     * Force paths to be normalized in the next call.
     *
     * Note: Normalizing option will be reset after the next method call.
     *
     * @return $this
     */
    public function unsafe()
    {
        $this->normalizeNext = true;

        return $this;
    }

    /**
     * Tell that all the paths are save to be used (already normalized) in the next call.
     *
     * Note: Normalizing option will be reset after the next method call.
     *
     * @return $this
     */
    public function safe()
    {
        $this->normalizeNext = false;

        return $this;
    }

    /**
     * Returns parent path. Empty path is returned if there are no segments remaining.
     *
     * Can be used recursively to get towards the root directory.
     *
     * @param string $path
     * @param int $levels
     * @return string
     * @throws \RuntimeException
     */
    public function parent(string $path, int $levels = 1): string
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        if ($this->normalizeNext !== false) {
            $path = $this->normalizePathPart($path);
        }
        $this->normalizeNext = null;

        if ($path === '') {
            return '';
        }

        [$scheme, $parent] = $this->dirnameInternal($scheme, $path, $levels);

        return $parent !== $path ? $this->toString($scheme, $parent) : '';
    }

    /**
     * Normalize path by cleaning up \ , /./ , // and /../
     *
     * @param string $path
     * @return string
     * @throws \RuntimeException
     */
    public function normalize(string $path): string
    {
        if ($this->normalizeNext === false) {
            $this->normalizeNext = null;

            return $path;
        }

        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        $path = $this->normalizePathPart($path);

        return $this->toString($scheme, $path);
    }

    /**
     * Stream safe dirname() replacement.
     *
     * @see   http://php.net/manual/en/function.dirname.php
     *
     * @param string $path
     * @param int $levels
     * @return string
     * @throws \RuntimeException
     */
    public function dirname(string $path, int $levels = 1): string
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        if ($this->normalizeNext || ($scheme && null === $this->normalizeNext)) {
            $path = $this->normalizePathPart($path);
        }
        $this->normalizeNext = null;

        [$scheme, $path] = $this->dirnameInternal($scheme, $path, $levels);

        return $this->toString($scheme, $path);
    }

    /**
     * Multi-byte and stream-safe pathinfo() replacement.
     *
     * Replacement for pathinfo(), but stream, multibyte and cross-platform safe.
     *
     * @see   http://www.php.net/manual/en/function.pathinfo.php
     *
     * @param string     $path     A filename or path, does not need to exist as a file
     * @param int|string $options Either a PATHINFO_* constant, or a string name to return only the specified piece
     *
     * @return array|string
     */
    public function pathinfo(string $path, int $options = null)
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        if ($this->normalizeNext || ($scheme && null === $this->normalizeNext)) {
            $path = $this->normalizePathPart($path);
        }
        $this->normalizeNext = null;

        return $this->pathinfoInternal($scheme, $path, $options);
    }

    /**
     * @param string|null $scheme
     * @param string $path
     * @param int $levels
     * @return array
     */
    protected function dirnameInternal(?string $scheme, string $path, int $levels = 1): array
    {
        $path = \dirname($path, $levels);

        if (null !== $scheme && $path === '.') {
            return [$scheme, ''];
        }

        return [$scheme, $path];
    }

    /**
     * @param string|null $scheme
     * @param string $path
     * @param int|null $options
     */
    protected function pathinfoInternal(?string $scheme, string $path, int $options = null)
    {
        $info = $options ? \pathinfo($path, $options) : \pathinfo($path);

        if (null !== $scheme) {
            $info['scheme'] = $scheme;
            $dirname = isset($info['dirname']) && $info['dirname'] !== '.' ? $info['dirname'] : null;

            if (null !== $dirname) {
                $info['dirname'] = $scheme . '://' . $dirname;
            } else {
                $info = ['dirname' => $scheme . '://'] + $info;
            }
        }

        return $info;
    }

    /**
     * Gets a 2-tuple of scheme (may be null) and hierarchical part of a filename (e.g. file:///tmp -> array(file, tmp)).
     *
     * @param string $filename
     * @return array
     */
    protected function getSchemeAndHierarchy(string $filename): array
    {
        $components = explode('://', $filename, 2);

        return 2 === \count($components) ? $components : [null, $components[0]];
    }

    /**
     * @param string|null $scheme
     * @param string $path
     * @return string
     */
    protected function toString(?string $scheme, string $path): string
    {
        if ($scheme) {
            return $scheme . '://' . $path;
        }

        return $path;
    }

    /**
     * @param string $path
     * @return string
     * @throws \RuntimeException
     */
    protected function normalizePathPart(string $path): string
    {
        // Quick check for empty path.
        if ($path === '' || $path === '.') {
            return '';
        }

        // Quick check for root.
        if ($path === '/') {
            return '/';
        }

        // If the last character is not '/' or any of '\', './', '//' and '..' are not found, path is clean and we're done.
        if ($path[-1] !== '/' && !preg_match('`(\\\\|\./|//|\.\.)`', $path)) {
            return $path;
        }

        // Convert backslashes
        $path = strtr($path, ['\\' => '/']);

        $parts = explode('/', $path);

        // Keep absolute paths.
        $root = '';
        if ($parts[0] === '') {
            $root = '/';
            array_shift($parts);
        }

        $list = [];
        foreach ($parts as $i => $part) {
            // Remove empty parts: // and /./
            if ($part === '' || $part === '.') {
                continue;
            }

            // Resolve /../ by removing path part.
            if ($part === '..') {
                $test = array_shift($list);
                if ($test === null) {
                    // Oops, user tried to access something outside of our root folder.
                    throw new \RuntimeException("Bad path {$path}");
                }
            }

            $list[] = $part;
        }

        // Build path back together.
        return $root . implode('/', $list);
    }
}
