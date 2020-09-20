<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

user_error('Grav\Common\Page\Medium\StaticResizeTrait is deprecated since Grav 1.7, use Grav\Common\Media\Traits\StaticResizeTrait instead', E_USER_DEPRECATED);

/**
 * Trait StaticResizeTrait
 * @package Grav\Common\Page\Medium
 * @deprecated 1.7 Use `Grav\Common\Media\Traits\StaticResizeTrait` instead
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
