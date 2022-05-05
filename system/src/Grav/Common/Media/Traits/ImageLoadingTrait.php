<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

/**
 * Adds <img loading="eager|lazy"> attribute.
 */
trait ImageLoadingTrait
{
    /**
     * Allows to set the loading attribute from Markdown or Twig
     *
     * @param string|null $value
     * @return static
     */
    public function loading(string $value = null)
    {
        if (null === $value) {
            $value = Grav::instance()['config']->get('system.images.defaults.loading', 'auto');
        }

        if ($value === 'auto') {
            $value = null;
        }

        $currentValue = $this->attributes['loading'] ?? null;
        if ($currentValue === $value) {
            return $this;
        }

        if ($value) {
            $this->attributes['loading'] = $value;
        } else {
            unset($this->attributes['loading']);
        }

        return $this;
    }
}
