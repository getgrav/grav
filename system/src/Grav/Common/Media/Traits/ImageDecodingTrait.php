<?php

/**
 * @package    Grav\Common\Media
 * @author     Pedro Moreno https://github.com/pmoreno-rodriguez
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

/**
 * Trait ImageDecodingTrait
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
        if (null === $value) {
            $value = Grav::instance()['config']->get('system.images.defaults.decoding', 'auto');
        }

        // Validate the provided value (similar to loading)
        if ($value !== null && $value !== 'auto') {
            $this->attributes['decoding'] = $value;
        }

        return $this;
    }

}