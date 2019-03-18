<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Filesystem
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Filesystem;

use Grav\Framework\Filesystem\Interfaces\FilesystemInterface;

class Filesystem implements FilesystemInterface
{
    /** @var bool|null */
    private $normalize;

    /** @var static */
    static protected $default;

    /** @var static */
    static protected $unsafe;

    /** @var static */
    static protected $safe;

    /**
     * @param bool|null $normalize See $this->setNormalization()
     *
     * @return Filesystem
     */
    public static function getInstance(bool $normalize = null): Filesystem
    {
        if ($normalize === true) {
            $instance = &static::$safe;
        } elseif ($normalize === false) {
            $instance = &static::$unsafe;
        } else {
            $instance = &static::$default;
        }

        if (null === $instance) {
            $instance = new static($normalize);
        }

        return $instance;
    }

    /**
     * Always use Filesystem::getInstance() instead.
     *
     * @param bool|null $normalize
     */
    protected function __construct(bool $normalize = null)
    {
        $this->normalize = $normalize;
    }

    /**
     * Set path normalization.
     *
     * Default option enables normalization for the streams only, but you can force the normalization to be either
     * on or off for every path. Disabling path normalization speeds up the calls, but may cause issues if paths were
     * not normalized.
     *
     * @param bool|null $normalize
     *
     * @return Filesystem
     */
    public function setNormalization(bool $normalize = null): self
    {
        return static::getInstance($normalize);
    }

    /**
     * Force all paths to be normalized.
     *
     * @return static
     */
    public function unsafe(): self
    {
        return static::getInstance(true);
    }

    /**
     * Force all paths not to be normalized (speeds up the calls if given paths are known to be normalized).
     *
     * @return static
     */
    public function safe(): self
    {
        return static::getInstance(false);
    }

    /**
     * {@inheritdoc}
     * @see FilesystemInterface::parent()
     */
    public function parent(string $path, int $levels = 1): string
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        if ($this->normalize !== false) {
            $path = $this->normalizePathPart($path);
        }

        if ($path === '' || $path === '.') {
            return '';
        }

        [$scheme, $parent] = $this->dirnameInternal($scheme, $path, $levels);

        return $parent !== $path ? $this->toString($scheme, $parent) : '';
    }

    /**
     * {@inheritdoc}
     * @see FilesystemInterface::normalize()
     */
    public function normalize(string $path): string
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        $path = $this->normalizePathPart($path);

        return $this->toString($scheme, $path);
    }

    /**
     * {@inheritdoc}
     * @see FilesystemInterface::dirname()
     */
    public function dirname(string $path, int $levels = 1): string
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        if ($this->normalize || ($scheme && null === $this->normalize)) {
            $path = $this->normalizePathPart($path);
        }

        [$scheme, $path] = $this->dirnameInternal($scheme, $path, $levels);

        return $this->toString($scheme, $path);
    }

    /**
     * {@inheritdoc}
     * @see FilesystemInterface::pathinfo()
     */
    public function pathinfo(string $path, int $options = null)
    {
        [$scheme, $path] = $this->getSchemeAndHierarchy($path);

        if ($this->normalize || ($scheme && null === $this->normalize)) {
            $path = $this->normalizePathPart($path);
        }

        return $this->pathinfoInternal($scheme, $path, $options);
    }

    /**
     * @param string|null $scheme
     * @param string $path
     * @param int $levels
     *
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
     *
     * @return array
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
     *
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
     *
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
     *
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
