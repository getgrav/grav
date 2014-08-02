<?php

namespace Gregwar\Image\Source;

use Gregwar\Image\Image;

/**
 * Open an image from a file
 */
class File extends Source
{
    protected $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function correct()
    {
        return false !== @exif_imagetype($this->file);
    }

    public function guessType()
    {
        if (function_exists('exif_imagetype')) {
            $type = @exif_imagetype($this->file);

            if (false !== $type) {
                if ($type == IMAGETYPE_JPEG) {
                    return 'jpeg';
                }

                if ($type == IMAGETYPE_GIF) {
                    return 'gif';
                }

                if ($type == IMAGETYPE_PNG) {
                    return 'png';
                }
            }
        }

        $parts = explode('.', $this->file);
        $ext = strtolower($parts[count($parts)-1]);

        if (isset(Image::$types[$ext])) {
            return Image::$types[$ext];
        }

        return 'jpeg';
    }

    public function getInfos()
    {
        return $this->file;
    }
}
