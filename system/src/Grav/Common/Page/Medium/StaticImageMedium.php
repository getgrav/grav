<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Traits\ImageLoadingTrait;
use Grav\Common\Media\Traits\StaticResizeTrait;

/**
 * Class StaticImageMedium
 * @package Grav\Common\Page\Medium
 *
 * @property int|null $width
 * @property int|null $height
 */
class StaticImageMedium extends Medium implements ImageMediaInterface
{
    use StaticResizeTrait;
    use ImageLoadingTrait;

    /**
     * Get basic file info.
     *
     * @return array
     */
    public function getInfo(): array
    {
        return [
                'width' => $this->width,
                'height' => $this->height,
            ] + parent::getInfo();
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  bool $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, bool $reset = true): array
    {
        if (empty($attributes['src'])) {
            $attributes['src'] = $this->url($reset);
        }

        return ['name' => 'img', 'attributes' => $attributes];
    }

    /**
     * @return $this
     */
    public function higherQualityAlternative(): ImageMediaInterface
    {
        return $this;
    }
}
