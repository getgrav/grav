<?php

namespace Gregwar\Image\Exceptions;

class GenerationError extends \Exception
{
    public function __construct($newNewFile)
    {
        $this->newNewFile = $newNewFile;
    }

    public function getNewFile()
    {
        return $this->newNewFile;
    }
}
