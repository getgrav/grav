<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use RecursiveFilterIterator;
use RecursiveIterator;
use SplFileInfo;
use function in_array;

/**
 * Class RecursiveDirectoryFilterIterator
 * @package Grav\Common\Filesystem
 */
class RecursiveDirectoryFilterIterator extends RecursiveFilterIterator
{
    /** @var string */
    protected static $root;
    /** @var array */
    protected static $ignore_folders;
    /** @var array */
    protected static $ignore_files;

    /**
     * Create a RecursiveFilterIterator from a RecursiveIterator
     *
     * @param RecursiveIterator $iterator
     * @param string $root
     * @param array $ignore_folders
     * @param array $ignore_files
     */
    public function __construct(RecursiveIterator $iterator, $root, $ignore_folders, $ignore_files)
    {
        parent::__construct($iterator);

        $this::$root = $root;
        $this::$ignore_folders = $ignore_folders;
        $this::$ignore_files = $ignore_files;
    }

    /**
     * Check whether the current element of the iterator is acceptable
     *
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        /** @var SplFileInfo $file */
        $file = $this->current();
        $filename = $file->getFilename();
        $relative_filename = str_replace($this::$root . '/', '', $file->getPathname());

        if ($file->isDir()) {
            if (in_array($relative_filename, $this::$ignore_folders, true)) {
                return false;
            }
            if (!in_array($filename, $this::$ignore_files, true)) {
                return true;
            }
        } elseif ($file->isFile() && !in_array($filename, $this::$ignore_files, true)) {
            return true;
        }
        return false;
    }

    /**
     * @return RecursiveDirectoryFilterIterator|RecursiveFilterIterator
     */
    public function getChildren()
    {
        /** @var RecursiveDirectoryFilterIterator $iterator */
        $iterator = $this->getInnerIterator();

        return new self($iterator->getChildren(), $this::$root, $this::$ignore_folders, $this::$ignore_files);
    }
}
