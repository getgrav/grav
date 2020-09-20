<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

/**
 * Trait StaticResizeTrait
 * @package Grav\Common\Media\Traits
 */
trait StaticResizeTrait
{
    /**
     * Resize media by setting attributes
     *
     * @param  int|null $width
     * @param  int|null $height
     * @return $this
     */
    public function resize($width = null, $height = null)
    {
        $this->styleAttributes['width'] = $width . 'px';
        $this->styleAttributes['height'] = $height . 'px';

        return $this;
    }
}
