<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2024 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

/**
 * Trait ImageLoadingTrait
 * @package Grav\Common\Media\Traits
 */

trait ImageDecodingTrait
{
    /**
     * Allows to set the decoding attribute from Markdown or Twig
     *
     * @param string|null $value
     * @return $this
     */
    public function decoding($value = null)
    {
        $validValues = ['sync', 'async', 'auto'];

        if (null === $value) {
            $value = Grav::instance()['config']->get('system.images.defaults.decoding', 'auto');
        }

        // Validate the provided value
        if ($value && in_array($value, $validValues, true)) {
            $this->attributes['decoding'] = $value;
        }

        return $this;
    }
}