<?php
/**
 * @package    Grav.Common.FileSystem
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

abstract class Archiver
{
    protected $options = [
        'exclude_files' => ['.DS_Store'],
        'exclude_paths' => []
    ];

    protected $archive_file;

    public static function create($compression)
    {
        if ($compression == 'zip') {
            return new ZipArchiver();
        } else {
            return new ZipArchiver();
        }
    }

    public function setArchive($archive_file)
    {
        $this->archive_file = $archive_file;
        return $this;
    }

    public function setOptions($options)
    {
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
