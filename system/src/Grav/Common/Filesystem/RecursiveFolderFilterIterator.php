<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use Grav\Common\Grav;
use RecursiveIterator;
use SplFileInfo;
use function in_array;

/**
 * Class RecursiveFolderFilterIterator
 * @package Grav\Common\Filesystem
 */
class RecursiveFolderFilterIterator extends \RecursiveFilterIterator
{
    /** @var array */
    protected static $ignore_folders;

    /**
     * Create a RecursiveFilterIterator from a RecursiveIterator
     *
     * @param RecursiveIterator $iterator
     * @param array $ignore_folders
     */
    public function __construct(RecursiveIterator $iterator, $ignore_folders = [])
    {
        parent::__construct($iterator);

        if (empty($ignore_folders)) {
            $ignore_folders = Grav::instance()['config']->get('system.pages.ignore_folders');
        }

        $this::$ignore_folders = $ignore_folders;
    }

    /**
     * Check whether the current element of the iterator is acceptable
     *
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        /** @var SplFileInfo $current */
        $current = $this->current();

        return $current->isDir() && !in_array($current->getFilename(), $this::$ignore_folders, true);
    }
}
