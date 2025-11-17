<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
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
    public function accept() :bool
    {
        /** @var SplFileInfo $file */
        $file = $this->current();
        $filename = $file->getFilename();
        $relative_filename = str_replace($this::$root . '/', '', $file->getPathname());

        if ($file->isDir()) {
            // Check if the directory path is in the ignore list
            if (in_array($relative_filename, $this::$ignore_folders, true)) {
                return false;
            }
            // Check if any parent directory is in the ignore list
            foreach ($this::$ignore_folders as $ignore_folder) {
                $ignore_folder = trim($ignore_folder, '/');
                if (strpos($relative_filename, $ignore_folder . '/') === 0 || $relative_filename === $ignore_folder) {
                    return false;
                }
            }
            if (!$this->matchesPattern($filename, $this::$ignore_files)) {
                return true;
            }
        } elseif ($file->isFile() && !$this->matchesPattern($filename, $this::$ignore_files)) {
            return true;
        }
        return false;
    }
    
    /**
     * Check if filename matches any pattern in the list
     *
     * @param string $filename
     * @param array $patterns
     * @return bool
     */
    protected function matchesPattern($filename, $patterns)
    {
        foreach ($patterns as $pattern) {
            // Check for exact match
            if ($filename === $pattern) {
                return true;
            }
            // Check for extension patterns like .pdf
            if (strpos($pattern, '.') === 0 && substr($filename, -strlen($pattern)) === $pattern) {
                return true;
            }
            // Check for wildcard patterns
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $filename)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return RecursiveDirectoryFilterIterator|RecursiveFilterIterator
     */
    public function getChildren() :RecursiveFilterIterator
    {
        /** @var RecursiveDirectoryFilterIterator $iterator */
        $iterator = $this->getInnerIterator();

        return new self($iterator->getChildren(), $this::$root, $this::$ignore_folders, $this::$ignore_files);
    }
}
