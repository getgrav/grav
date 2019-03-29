<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use Grav\Common\Utils;

abstract class Archiver
{
    protected $options = [
        'exclude_files' => ['.DS_Store'],
        'exclude_paths' => []
    ];

    protected $archive_file;

    public static function create($compression)
    {
        if ($compression === 'zip') {
            return new ZipArchiver();
        }

        return new ZipArchiver();
    }

    public function setArchive($archive_file)
    {
        $this->archive_file = $archive_file;
        return $this;
    }

    public function setOptions($options)
    {
        // Set infinite PHP execution time if possible.
        if (function_exists('set_time_limit') && !Utils::isFunctionDisabled('set_time_limit')) {
            set_time_limit(0);
        }

        $this->options = $options + $this->options;
        return $this;
    }

    public abstract function compress($folder, callable $status = null);

    public abstract function extract($destination, callable $status = null);

    public abstract function addEmptyFolders($folders, callable $status = null);

    protected function getArchiveFiles($rootPath)
    {
        $exclude_paths = $this->options['exclude_paths'];
        $exclude_files = $this->options['exclude_files'];
        $dirItr    = new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS | \FilesystemIterator::UNIX_PATHS);
        $filterItr = new RecursiveDirectoryFilterIterator($dirItr, $rootPath, $exclude_paths, $exclude_files);
        $files       = new \RecursiveIteratorIterator($filterItr, \RecursiveIteratorIterator::SELF_FIRST);

        return $files;
    }

}
