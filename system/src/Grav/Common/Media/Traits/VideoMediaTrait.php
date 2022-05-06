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
     * @param string|null $poster
     * @return $this
     * @phpstan-impure
     */
    public function poster(string $poster = null)
    {
        $currentPoster = $this->attributes['poster'] ?? null;
        if ($currentPoster !== $poster) {
            return $this;
        }

        if ($poster) {
            $this->attributes['poster'] = $poster;
        } else {
            unset($this->attributes['poster']);
        }

        return $this;
    }

    /**
     * Allows to set the playsinline attribute
     *
     * @param bool $status
     * @return $this
     * @phpstan-impure
     */
    public function playsinline(bool $status = false)
    {
        $currentStatus = (bool)($this->attributes['playsinline'] ?? null);
        if ($currentStatus === $status) {
            return $this;
        }

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
     * @return array
     * @phpstan-impure
     */
    protected function sourceParsedownElement(array $attributes): array
    {
        $location = $this->url(false);

        return [
            'name' => 'video',
            'rawHtml' => '<source src="' . $location . '">Your browser does not support the video tag.',
            'attributes' => $attributes
        ];
    }
}
