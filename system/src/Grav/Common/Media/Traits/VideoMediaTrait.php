<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2024 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Page\Medium\ThumbnailImageMedium;

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
     * @param array $attributes
     * @param bool $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, $reset = true)
    {
        $location = $this->url($reset);

        if (!isset($attributes['poster']) || ($attributes['poster'] !== 0 && $attributes['poster'] !== '0')) {
            if ($this->thumbnailExists('page')) {
                $thumb = $this->get("thumbnails.page", false);
                if ($thumb) {
                    $thumb = $thumb instanceof ThumbnailImageMedium ? $thumb : MediumFactory::fromFile($thumb, ['type' => 'thumbnail']);
                    $attributes['poster'] = $thumb->url();
                }
            }
        }
        if (isset($attributes['poster']) && ($attributes['poster'] === 0 || $attributes['poster'] === '0')) {
            unset($attributes['poster']);
        }

        return [
            'name' => 'video',
            'rawHtml' => '<source src="' . $location . '">Your browser does not support the video tag.',
            'attributes' => $attributes
        ];
    }
}
