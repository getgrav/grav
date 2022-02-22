<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

/**
 * Trait VideoMediaTrait
 * @package Grav\Common\Media\Traits
 */
trait VideoMediaTrait
{
    use StaticResizeTrait;
    use MediaPlayerTrait;

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
     * Allows to set the playsinline attribute
     *
     * @param bool $status
     * @return $this
     */
    public function playsinline($status = false)
    {
        if ($status) {
            $this->attributes['playsinline'] = 'playsinline';
        } else {
            unset($this->attributes['playsinline']);
        }

        return $this;
    }

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
            'rawHtml' => '<source src="' . $location . '">Your browser does not support the video tag.',
            'attributes' => $attributes
        ];
    }
}
