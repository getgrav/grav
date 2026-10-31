<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

/**
 * Trait ImageLoadingTrait
 * @package Grav\Common\Media\Traits
 */
trait ImageLoadingTrait
{
    /**
     * Allows to set the loading attribute from Markdown or Twig
     *
     * @param string|null $value
     * @return $this
     */
    public function loading($value = null)
    {
        if (null === $value) {
            $value = Grav::instance()['config']->get('system.images.defaults.loading', 'auto');
        }

        // Only the keywords the attribute defines are kept
        $value = strtolower((string) $value);
        if (in_array($value, ['lazy', 'eager'], true)) {
            $this->attributes['loading'] = $value;
        }

        return $this;
    }
}
