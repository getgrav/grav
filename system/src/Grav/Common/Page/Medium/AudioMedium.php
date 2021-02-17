<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Media\Interfaces\AudioMediaInterface;
use Grav\Common\Media\Traits\AudioMediaTrait;

/**
 * Class AudioMedium
 * @package Grav\Common\Page\Medium
 */
class AudioMedium extends Medium implements AudioMediaInterface
{
    use AudioMediaTrait;

    /**
     * Reset medium.
     *
     * @return $this
     */
    public function reset()
    {
        parent::reset();

        $this->resetPlayer();

        return $this;
    }
}
