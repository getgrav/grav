<?php
namespace Grav\Common\Filesystem;

use Grav\Common\GravTrait;

class RecursiveFolderFilterIterator extends \RecursiveFilterIterator
{
    use GravTrait;

    protected static $folder_ignores;

    public function __construct(\RecursiveIterator $iterator)
    {
        parent::__construct($iterator);
        if (empty($this::$folder_ignores)) {
            $this::$folder_ignores = self::getGrav()['config']->get('system.pages.ignore_folders');
        }
    }

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
