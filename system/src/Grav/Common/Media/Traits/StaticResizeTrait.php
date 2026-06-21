<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
        // Width/height are pixel dimensions reachable from editor Markdown
        // (`?resize=W,H`). Coerce them to integers before they enter
        // $styleAttributes so a crafted value such as `100;position:fixed;…`
        // can't break out of the `width:` declaration and inject extra CSS
        // into the rendered `<img style="…">`. resize() writes keyed style
        // values directly and so bypassed the style() sanitizer.
        // GHSA-ffmg-hfvg-jhg9 (follow-up to GHSA-pmf8-g7c8-7v54).
        $width = is_numeric($width) ? (int) $width : 0;
        $height = is_numeric($height) ? (int) $height : 0;

        if ($width > 0) {
            $this->styleAttributes['width'] = $width . 'px';
        } else {
            unset($this->styleAttributes['width']);
        }
        if ($height > 0) {
            $this->styleAttributes['height'] = $height . 'px';
        } else {
            unset($this->styleAttributes['height']);
        }

        return $this;
    }
}
