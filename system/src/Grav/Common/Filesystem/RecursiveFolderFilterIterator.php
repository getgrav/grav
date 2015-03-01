<?php
namespace Grav\Common\Filesystem;

class RecursiveFolderFilterIterator extends \RecursiveFilterIterator
{
    public function accept()
    {
        // only accept directories
        return $this->current()->isDir();
    }
}
