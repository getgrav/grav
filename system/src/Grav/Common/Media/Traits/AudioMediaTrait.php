<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

/**
 * Trait AudioMediaTrait
 * @package Grav\Common\Media\Traits
 */
trait AudioMediaTrait
{
    use StaticResizeTrait;
    use MediaPlayerTrait;

    /**
     * Allows to set the controlsList behaviour
     * Separate multiple values with a hyphen
     *
     * @param string $controlsList
     * @return $this
     * @phpstan-impure
     */
    public function controlsList(string $controlsList)
    {
        $controlsList = str_replace('-', ' ', $controlsList);
        $currentList = $this->attributes['controlsList'] ?? null;

        if ($currentList === $controlsList) {
            return $this;
        }

        if ($controlsList) {
            $this->attributes['controlsList'] = $controlsList;
        } else {
            unset($this->attributes['controlsList']);
        }

        return $this;
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  bool $reset
     * @return array
     * @phpstan-pure
     */
    protected function sourceParsedownElement(array $attributes, bool $reset = true): array
    {
        $location = $this->url($reset);

        return [
            'name' => 'audio',
            'rawHtml' => '<source src="' . $location . '">Your browser does not support the audio tag.',
            'attributes' => $attributes
        ];
    }
}
