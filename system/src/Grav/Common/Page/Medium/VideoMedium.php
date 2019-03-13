<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
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
     * @param  bool $reset
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
     * Allows to set or remove the HTML5 default controls
     *
     * @param bool $display
     * @return $this
     */
    public function controls($display = true)
    {
        if($display) {
            $this->attributes['controls'] = true;
        } else {
            unset($this->attributes['controls']);
        }

        return $this;
    }

    /**
     * Allows to set the video's poster image
     *
     * @param string $urlImage
     * @return $this
     */
    public function poster($urlImage)
    {
        $this->attributes['poster'] = $urlImage;

        return $this;
    }

    /**
     * Allows to set the loop attribute
     *
     * @param bool $status
     * @return $this
     */
    public function loop($status = false)
    {
        if($status) {
            $this->attributes['loop'] = true;
        } else {
            unset($this->attributes['loop']);
        }

        return $this;
    }

    /**
     * Allows to set the autoplay attribute
     *
     * @param bool $status
     * @return $this
     */
    public function autoplay($status = false)
    {
        if ($status) {
            $this->attributes['autoplay'] = '';
        } else {
            unset($this->attributes['autoplay']);
        }

        return $this;
    }

    /**
     * Allows ability to set the preload option
     *
     * @param null $status
     * @return $this
     */
    public function preload($status = null)
    {
        if ($status) {
            $this->attributes['preload'] = $status;
        } else {
            unset($this->attributes['preload']);
        }

        return $this;
    }

    /**
     * Allows to set the playsinline attribute
     *
     * @param bool $status
     * @return $this
     */
    public function playsinline($status = false)
    {
        if($status) {
            $this->attributes['playsinline'] = true;
        } else {
            unset($this->attributes['playsinline']);
        }

        return $this;
    }

    /**
     * Allows to set the muted attribute
     *
     * @param bool $status
     * @return $this
     */
    public function muted($status = false)
    {
        if($status) {
            $this->attributes['muted'] = true;
        } else {
            unset($this->attributes['muted']);
        }

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
