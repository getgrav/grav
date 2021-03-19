<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use FilesystemIterator;
use Grav\Common\Utils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function function_exists;

/**
 * Class Archiver
 * @package Grav\Common\Filesystem
 */
abstract class Archiver
{
    /** @var array */
    protected $options = [
        'exclude_files' => ['.DS_Store'],
        'exclude_paths' => []
    ];

    /** @var string */
    protected $archive_file;

    /**
     * @param string $compression
     * @return ZipArchiver
     */
    public static function create($compression)
    {
        if ($compression === 'zip') {
            return new ZipArchiver();
        }

        return new ZipArchiver();
    }

    /**
     * @param string $archive_file
     * @return $this
     */
    public function setArchive($archive_file)
    {
        $this->archive_file = $archive_file;

        return $this;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        // Set infinite PHP execution time if possible.
        if (Utils::functionExists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->options = $options + $this->options;

        return $this;
    }

    /**
     * @param string $folder
     * @param callable|null $status
     * @return $this
     */
    abstract public function compress($folder, callable $status = null);

    /**
     * @param string $destination
     * @param callable|null $status
     * @return $this
     */
    abstract public function extract($destination, callable $status = null);

    /**
     * @param array $folders
     * @param callable|null $status
     * @return $this
     */
    abstract public function addEmptyFolders($folders, callable $status = null);

    /**
     * @param string $rootPath
     * @return RecursiveIteratorIterator
     */
    protected function getArchiveFiles($rootPath)
    {
        $exclude_paths = $this->options['exclude_paths'];
        $exclude_files = $this->options['exclude_files'];
        $dirItr    = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::UNIX_PATHS);
        $filterItr = new RecursiveDirectoryFilterIterator($dirItr, $rootPath, $exclude_paths, $exclude_files);
        $files     = new RecursiveIteratorIterator($filterItr, RecursiveIteratorIterator::SELF_FIRST);

        return $files;
    }
}
