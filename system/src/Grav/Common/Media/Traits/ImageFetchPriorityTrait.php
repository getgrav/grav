<?php

/**
 * @package    Grav\Common\Media
 * @author     Pedro Moreno https://github.com/pmoreno-rodriguez
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

/**
 * Trait ImageFetchPriorityTrait
 * @package Grav\Common\Media\Traits
 */

trait ImageFetchPriorityTrait
{
    /**
     * Allows to set the fetchpriority attribute from Markdown or Twig
     *
     * @param string|null $value
     * @return $this
     */
    public function fetchpriority($value = null)
    {
        if (null === $value) {
            $value = Grav::instance()['config']->get('system.images.defaults.fetchpriority', 'auto');
        }

        // Validate the provided value (similar to loading and decoding attributes)
        if ($value !== null && $value !== 'auto') {
            $this->attributes['fetchpriority'] = $value;
        }

        return $this;
    }

}