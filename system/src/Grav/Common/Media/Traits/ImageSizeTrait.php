<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

/**
 * Trait ImageSizeTrait
 * @package Grav\Common\Media\Traits
 */
trait ImageSizeTrait
{
    /**
     * Allows to set the height/width attributes from Markdown or Twig
     *
     * @param string|null $value
     * @return $this
     */
    public function size($value = null)
    {
        if (null === $value) {
            $value = Grav::instance()['config']->get('system.images.defaults.size', 'none');
        }
        if (
            $value && $value === 'auto' &&
            !array_key_exists('height', $this->attributes) &&
            !array_key_exists('width', $this->attributes)
        ) {
            $this->attributes['height'] = $this['height'];
            $this->attributes['width'] = $this['width'];
        }

        return $this;
    }
}