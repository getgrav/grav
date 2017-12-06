<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

class AudioMedium extends Medium
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
            'name' => 'audio',
            'text' => '<source src="' . $location . '">Your browser does not support the audio tag.',
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
        if($display)
        {
            $this->attributes['controls'] = true;
        }
        else
        {
            unset($this->attributes['controls']);
        }
        return $this;
    }

    /**
     * Allows to set the preload behaviour
     *
     * @param $preload
     * @return $this
     */
    public function preload($preload)
    {
        $validPreloadAttrs = array('auto','metadata','none');
        
        if (in_array($preload, $validPreloadAttrs))
        {
            $this->attributes['preload'] = $preload;
        }
        return $this;
    }

    /**
     * Allows to set the controlsList behaviour
     * Separate multiple values with a hyphen
     *
     * @param $controlsList
     * @return $this
     */
    public function controlsList($controlsList)
    {
        $controlsList = str_replace('-', ' ', $controlsList);
        $this->attributes['controlsList'] = $controlsList;
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
        if($status)
        {
            $this->attributes['muted'] = true;
        }
        else
        {
            unset($this->attributes['muted']);
        }
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
        if($status)
        {
            $this->attributes['loop'] = true;
        }
        else
        {
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
        if($status)
        {
            $this->attributes['autoplay'] = true;
        }
        else
        {
            unset($this->attributes['autoplay']);
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
