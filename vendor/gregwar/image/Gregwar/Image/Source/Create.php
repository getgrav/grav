<?php

namespace Gregwar\Image\Source;

/**
 * Creates a new image from scratch
 */
class Create extends Source
{
    protected $width;
    protected $height;

    public function __construct($width, $height)
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function getInfos()
    {
        return array($this->width, $this->height);
    }

    public function correct()
    {
        return $this->width > 0 && $this->height > 0;
    }
}
