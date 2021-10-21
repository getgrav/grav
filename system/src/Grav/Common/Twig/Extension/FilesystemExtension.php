<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Extension;

use Grav\Common\Grav;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Class FilesystemExtension
 * @package Grav\Common\Twig\Extension
 */
class FilesystemExtension extends AbstractExtension
{
    /** @var UniformResourceLocator */
    private $locator;

    public function __construct()
    {
        $this->locator = Grav::instance()['locator'];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters()
    {
        return [
            new TwigFilter('file_exists', [$this, 'file_exists']),
            new TwigFilter('fileatime', [$this, 'fileatime']),
            new TwigFilter('filectime', [$this, 'filectime']),
            new TwigFilter('filemtime', [$this, 'filemtime']),
            new TwigFilter('filesize', [$this, 'filesize']),
            new TwigFilter('filetype', [$this, 'filetype']),
            new TwigFilter('is_dir', [$this, 'is_dir']),
            new TwigFilter('is_file', [$this, 'is_file']),
            new TwigFilter('is_link', [$this, 'is_link']),
            new TwigFilter('is_readable', [$this, 'is_readable']),
            new TwigFilter('is_writable', [$this, 'is_writable']),
            new TwigFilter('is_writeable', [$this, 'is_writable']),
            new TwigFilter('lstat', [$this, 'lstat']),
            new TwigFilter('getimagesize', [$this, 'getimagesize']),
            new TwigFilter('exif_read_data', [$this, 'exif_read_data']),
            new TwigFilter('read_exif_data', [$this, 'exif_read_data']),
            new TwigFilter('exif_imagetype', [$this, 'exif_imagetype']),
            new TwigFilter('hash_file', [$this, 'hash_file']),
            new TwigFilter('hash_hmac_file', [$this, 'hash_hmac_file']),
            new TwigFilter('md5_file', [$this, 'md5_file']),
            new TwigFilter('sha1_file', [$this, 'sha1_file']),
            new TwigFilter('get_meta_tags', [$this, 'get_meta_tags']),
            new TwigFilter('pathinfo', [$this, 'pathinfo']),
        ];
    }

    /**
     * Return a list of all functions.
     *
     * @return TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('file_exists', [$this, 'file_exists']),
            new TwigFunction('fileatime', [$this, 'fileatime']),
            new TwigFunction('filectime', [$this, 'filectime']),
            new TwigFunction('filemtime', [$this, 'filemtime']),
            new TwigFunction('filesize', [$this, 'filesize']),
            new TwigFunction('filetype', [$this, 'filetype']),
            new TwigFunction('is_dir', [$this, 'is_dir']),
            new TwigFunction('is_file', [$this, 'is_file']),
            new TwigFunction('is_link', [$this, 'is_link']),
            new TwigFunction('is_readable', [$this, 'is_readable']),
            new TwigFunction('is_writable', [$this, 'is_writable']),
            new TwigFunction('is_writeable', [$this, 'is_writable']),
            new TwigFunction('lstat', [$this, 'lstat']),
            new TwigFunction('getimagesize', [$this, 'getimagesize']),
            new TwigFunction('exif_read_data', [$this, 'exif_read_data']),
            new TwigFunction('read_exif_data', [$this, 'exif_read_data']),
            new TwigFunction('exif_imagetype', [$this, 'exif_imagetype']),
            new TwigFunction('hash_file', [$this, 'hash_file']),
            new TwigFunction('hash_hmac_file', [$this, 'hash_hmac_file']),
            new TwigFunction('md5_file', [$this, 'md5_file']),
            new TwigFunction('sha1_file', [$this, 'sha1_file']),
            new TwigFunction('get_meta_tags', [$this, 'get_meta_tags']),
            new TwigFunction('pathinfo', [$this, 'pathinfo']),
        ];
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function file_exists($filename): bool
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return file_exists($filename);
    }

    /**
     * @param string $filename
     * @return int|false
     */
    public function fileatime($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return fileatime($filename);
    }

    /**
     * @param string $filename
     * @return int|false
     */
    public function filectime($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return filectime($filename);
    }

    /**
     * @param string $filename
     * @return int|false
     */
    public function filemtime($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return filemtime($filename);
    }

    /**
     * @param string $filename
     * @return int|false
     */
    public function filesize($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return filesize($filename);
    }

    /**
     * @param string $filename
     * @return string|false
     */
    public function filetype($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return filetype($filename);
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function is_dir($filename): bool
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return is_dir($filename);
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function is_file($filename): bool
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return is_file($filename);
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function is_link($filename): bool
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return is_link($filename);
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function is_readable($filename): bool
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return is_readable($filename);
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function is_writable($filename): bool
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return is_writable($filename);
    }

    /**
     * @param string $filename
     * @return array|false
     */
    public function lstat($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return lstat($filename);
    }

    /**
     * @param string $filename
     * @return array|false
     */
    public function getimagesize($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return getimagesize($filename);
    }

    /**
     * @param string $file
     * @param string|null $required_sections
     * @param bool $as_arrays
     * @param bool $read_thumbnail
     * @return array|false
     */
    public function exif_read_data($file, ?string $required_sections, bool $as_arrays = false, bool $read_thumbnail = false)
    {
        if (!Utils::functionExists('exif_read_data') || !$this->checkFilename($file)) {
            return false;
        }

        return exif_read_data($file, $required_sections, $as_arrays, $read_thumbnail);
    }

    /**
     * @param string $filename
     * @return string|false
     */
    public function exif_imagetype($filename)
    {
        if (!Utils::functionExists('exif_imagetype') || !$this->checkFilename($filename)) {
            return false;
        }

        return @exif_imagetype($filename);
    }

    /**
     * @param string $algo
     * @param string $filename
     * @param bool $binary
     * @return string|false
     */
    public function hash_file(string $algo, string $filename, bool $binary = false)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return hash_file($algo, $filename, $binary);
    }

    /**
     * @param string $algo
     * @param string $data
     * @param string $key
     * @param bool $binary
     * @return string|false
     */
    public function hash_hmac_file(string $algo, string $data, string $key, bool $binary = false)
    {
        if (!$this->checkFilename($data)) {
            return false;
        }

        return hash_hmac_file($algo, $data, $key, $binary);
    }

    /**
     * @param string $filename
     * @param bool $binary
     * @return string|false
     */
    public function md5_file($filename, bool $binary = false)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return md5_file($filename, $binary);
    }

    /**
     * @param string $filename
     * @param bool $binary
     * @return string|false
     */
    public function sha1_file($filename, bool $binary = false)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return sha1_file($filename, $binary);
    }

    /**
     * @param string $filename
     * @return array|false
     */
    public function get_meta_tags($filename)
    {
        if (!$this->checkFilename($filename)) {
            return false;
        }

        return get_meta_tags($filename);
    }

    /**
     * @param string $path
     * @param int|null $flags
     * @return string|string[]
     */
    public function pathinfo($path, $flags = null)
    {
        if (null !== $flags) {
            return pathinfo($path, (int)$flags);
        }

        return pathinfo($path);
    }

    /**
     * @param string $filename
     * @return bool
     */
    private function checkFilename($filename): bool
    {
        return is_string($filename) && (!str_contains($filename, '://') || $this->locator->isStream($filename));
    }
}
