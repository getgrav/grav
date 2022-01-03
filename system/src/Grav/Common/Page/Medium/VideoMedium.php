<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Media\Interfaces\VideoMediaInterface;
use Grav\Common\Media\Traits\VideoMediaTrait;

/**
 * Class VideoMedium
 * @package Grav\Common\Page\Medium
 */
class VideoMedium extends Medium implements VideoMediaInterface
{
    use VideoMediaTrait;

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
