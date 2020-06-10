<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;

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
        if (is_null($value)) {
            $value = Grav::instance()['config']->get('images.defaults.loading', 'auto');
        }
        if ($value && $value !== 'auto') {
            $this->attributes['loading'] = $value;
        }

        return $this;
    }
}
