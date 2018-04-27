<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

class VideoMedium extends Medium
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
        $path = parse_url($location, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimeType = \Grav\Common\Utils::getMimeByExtension($extension);

        if (!isset($attributes['poster'])) {
            $poster = $this->findPoster($location);
            if ($poster) {
                $attributes['poster'] = $poster;
            }
        }

        return [
            'name' => 'video',
            'text' => '<source src="' . $location . '" type="' . $mimeType . '">Your browser does not support the video tag.',
            'attributes' => $attributes
        ];
    }

    /**
     * Try to find a poster image for the video.
     *
     * Looks at the same location as the video file is:
     * - $filename.jpg
     * - $filename.png
     * - $filename.$ext.thumb.jpg
     * - $filename.$ext.thumb.png
     *
     * @param string $location Video location
     *
     * @return string Poster URL or false
     */
    protected function findPoster($location)
    {
        $noQuery = preg_replace('#\\?.*$#', '', $location);
        $fullPath = GRAV_ROOT . $noQuery;

        if (!file_exists($fullPath)) {
            return false;
        }

        $parts = pathinfo($fullPath);
        $noExt = $parts['dirname'] . DIRECTORY_SEPARATOR . $parts['filename'];

        $possibilities = [
            $noExt . '.jpg',
            $noExt . '.png',
            $fullPath . '.thumb.jpg',
            $fullPath . '.thumb.png',
        ];

        foreach ($possibilities as $thumbLocation) {
            if (file_exists($thumbLocation)) {
                $relative = substr($thumbLocation, strlen(GRAV_ROOT));
                return $relative;
            }
        }
    }

    /**
     * Allows to set or remove the HTML5 default controls
     *
     * @param bool $display
     * @return $this
     */
    public function controls($display = true)
    {
        if($display) {
            $this->attributes['controls'] = true;
        } else {
            unset($this->attributes['controls']);
        }

        return $this;
    }

    /**
     * Allows to set the video's poster image
     *
     * @param $urlImage
     * @return $this
     */
    public function poster($urlImage)
    {
        $this->attributes['poster'] = $urlImage;

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
        if($status) {
            $this->attributes['loop'] = true;
        } else {
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
        if($status) {
            $this->attributes['autoplay'] = true;
        } else {
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
