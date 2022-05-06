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
     * @phpstan-pure
     */
    public function getInfo(): array
    {
        return [
                'width' => $this->width,
                'height' => $this->height,
            ] + parent::getInfo();
    }

    /**
     * @return array
     * @phpstan-pure
     */
    public function getMeta(): array
    {
        return [
                'width' => $this->width,
                'height' => $this->height,
            ] + parent::getMeta();
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @return array
     * @phpstan-pure
     */
    protected function sourceParsedownElement(array $attributes): array
    {
        if (empty($attributes['src'])) {
            $attributes['src'] = $this->url(false);
        }

        return ['name' => 'img', 'attributes' => $attributes];
    }

    /**
     * @return $this
     * @phpstan-pure
     */
    public function higherQualityAlternative(): ImageMediaInterface
    {
        return $this;
    }
}
