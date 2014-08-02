<?php

namespace Gregwar\Image\Source;

/**
 * An Image source
 */
class Source
{
    /**
     * Guess the type of the image
     */
    public function guessType()
    {
        return 'jpeg';
    }

    /**
     * Is this image correct ?
     */
    public function correct()
    {
        return true;
    }

    /**
     * Returns information about images, these informations should
     * change only if the original image changed
     */
    public function getInfos()
    {
        return null;
    }
}
