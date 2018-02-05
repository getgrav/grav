<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

trait StaticResizeTrait
{
    /**
     * Resize media by setting attributes
     *
     * @param  int $width
     * @param  int $height
     * @return $this
     */
    public function resize($width = null, $height = null)
    {
        $this->styleAttributes['width'] = $width . 'px';
        $this->styleAttributes['height'] = $height . 'px';

        return $this;
    }
}
