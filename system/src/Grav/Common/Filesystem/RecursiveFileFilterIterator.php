<?php
namespace Grav\Common\Filesystem;

class RecursiveFileFilterIterator extends \RecursiveFilterIterator
{
    public static $FILTERS = ['.DS_Store'];

    public function accept()
    {
        // Ensure any filtered file names are skipped
        return !in_array($this->current()->getFilename(), self::$FILTERS, true);
    }
}
