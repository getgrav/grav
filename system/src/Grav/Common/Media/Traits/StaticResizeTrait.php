<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
    public function resize(int $width = null, int $height = null)
    {
        if ($width) {
            $this->styleAttributes['width'] = (int)$width . 'px';
        } else {
            unset($this->styleAttributes['width']);
        }
        if ($height) {
            $this->styleAttributes['height'] = (int)$height . 'px';
        } else {
            unset($this->styleAttributes['height']);
        }

        return $this;
    }
}
