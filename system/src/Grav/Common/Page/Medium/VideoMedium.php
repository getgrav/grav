<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

class VideoMedium extends Medium
{
    use StaticResizeTrait;

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  boolean $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, $reset = true)
    {
        $location = $this->url($reset);

        return [
            'name' => 'video',
            'text' => '<source src="' . $location . '">Your browser does not support the video tag.',
            'attributes' => $attributes
        ];
    }


    /**
     * Set video autoplay on
     *
     * @return $this
     */
    public function autoplay()
    {
        $this->attributes['autoplay'] = true;

        return $this;
    }


    /**
     * Set video loop
     *
     * @return $this
     */
    public function loop()
    {
        $this->attributes['loop'] = true;

        return $this;
    }


    /**
     * Set controls visibility off
     *
     * @return $this
     */
    public function hideControls()
    {
        unset($this->attributes['controls']);

        return $this;
    }


    /**
     * Reset medium.
     *
     * @return $this
     */
    public function reset()
    {
        parent::reset();

        $this->attributes['controls'] = true;
        return $this;
    }
}
