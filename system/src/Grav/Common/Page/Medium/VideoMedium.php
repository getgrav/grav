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
 *
 * @property int|null $width
 * @property int|null $height
 */
class VideoMedium extends Medium implements VideoMediaInterface
{
    use VideoMediaTrait;

    /**
     * Reset medium.
     *
     * @return $this
     * @phpstan-impure
     */
    public function reset()
    {
        parent::reset();

        $this->resetPlayer();

        return $this;
    }

    /**
     * Get basic file info.
     *
     * @return array
     * @phpstan-pure
     */
    public function getInfo(): array
    {
        return [
                'width' => $this->width,
                'height' => $this->height,
            ] + parent::getInfo();
    }
}
