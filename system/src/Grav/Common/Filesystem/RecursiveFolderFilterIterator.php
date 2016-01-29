<?php
namespace Grav\Common\Filesystem;

use Grav\Common\GravTrait;

/**
 * Class RecursiveFolderFilterIterator
 * @package Grav\Common\Filesystem
 */
class RecursiveFolderFilterIterator extends \RecursiveFilterIterator
{
    use GravTrait;

    protected static $folder_ignores;

    /**
     * Create a RecursiveFilterIterator from a RecursiveIterator
     *
     * @param RecursiveIterator $iterator
     */
    public function __construct(\RecursiveIterator $iterator)
    {
        parent::__construct($iterator);
        if (empty($this::$folder_ignores)) {
            $this::$folder_ignores = self::getGrav()['config']->get('system.pages.ignore_folders');
        }
    }

    /**
     * Check whether the current element of the iterator is acceptable
     *
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        /** @var $current \SplFileInfo */
        $current = $this->current();

        if ($current->isDir() && !in_array($current->getFilename(), $this::$folder_ignores)) {
            return true;
        }
        return false;
    }
}
