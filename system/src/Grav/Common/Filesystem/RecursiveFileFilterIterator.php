<?php
namespace Grav\Common\Filesystem;

use Grav\Common\Utils;

class RecursiveFileFilterIterator extends \FilterIterator
{
    public function accept()
    {
        // Ensure only valid file names are skipped
        $current = $this->current()->getFilename();
        $accept = Utils::endsWith($current, '.md');

        return $accept;
    }
}
