<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use function in_array;

/**
 * Class implements audio object interface.
 */
trait MediaPlayerTrait
{
    /**
     * Allows to set or remove the HTML5 default controls
     *
     * @param bool $status
     * @return $this
     */
    public function controls($status = true)
    {
        if ($status) {
            $this->attributes['controls'] = 'controls';
        } else {
            unset($this->attributes['controls']);
        }

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
        if ($status) {
            $this->attributes['loop'] = 'loop';
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
        if ($status) {
            $this->attributes['autoplay'] = 'autoplay';
        } else {
            unset($this->attributes['autoplay']);
        }

        return $this;
    }

    /**
     * Allows to set the muted attribute
     *
     * @param bool $status
     * @return $this
     */
    public function muted($status = false)
    {
        if ($status) {
            $this->attributes['muted'] = 'muted';
        } else {
            unset($this->attributes['muted']);
        }

        return $this;
    }

    /**
     * Allows to set the preload behaviour
     *
     * @param string|null $preload
     * @return $this
     */
    public function preload($preload = null)
    {
        $validPreloadAttrs = ['auto', 'metadata', 'none'];

        if (null === $preload) {
            unset($this->attributes['preload']);
        } elseif (in_array($preload, $validPreloadAttrs, true)) {
            $this->attributes['preload'] = $preload;
        }

        return $this;
    }

    /**
     * Normalize attributes for HTML audio and video elements.
     *
     * The generic media pipeline always provides an alt attribute, but alt is
     * not valid on audio or video. Preserve non-empty alternative text as an
     * accessible name unless the caller already provided one explicitly.
     *
     * @param array $attributes
     * @return array
     */
    protected function normalizePlayerAttributes(array $attributes)
    {
        if (isset($attributes['alt'])) {
            if ($attributes['alt'] !== '' && empty($attributes['aria-label'])) {
                $attributes['aria-label'] = $attributes['alt'];
            }

            unset($attributes['alt']);
        }

        return $attributes;
    }

    /**
     * Reset player.
     */
    public function resetPlayer()
    {
        $this->attributes['controls'] = 'controls';
    }
}
