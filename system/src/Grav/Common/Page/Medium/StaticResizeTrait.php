<?php
namespace Grav\Common\Page\Medium;

trait StaticResizeTrait
{
    /**
     * Resize media by setting attributes
     *
     * @param  int $width
     * @param  int $height
     * @return Medium
     */
    public function resize($width = null, $height = null)
    {
        $this->attributes['width'] = $width;
        $this->attributes['height'] = $height;

        return $this;
    }
}
