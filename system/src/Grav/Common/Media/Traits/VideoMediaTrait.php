<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
            // Escape the URL before it lands in the rawHtml source string: it is
            // emitted verbatim (unlike an image `src`, which goes through an
            // escaped attribute), and the media URL carries an unencoded fragment
            // (urlHash only strips a leading `#`), so a crafted fragment would
            // otherwise break out of src="…" into live markup. (GHSA-6qw9-4vv5-jr97)
            'rawHtml' => '<source src="' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '">Your browser does not support the video tag.',
            'attributes' => $attributes
        ];
    }
}
