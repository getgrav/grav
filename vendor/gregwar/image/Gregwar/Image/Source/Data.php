<?php

namespace Gregwar\Image\Source;

/**
 * Having image in some string
 */
class Data extends Source
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getInfos()
    {
        return sha1($this->data);
    }
}
