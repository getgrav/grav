<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
     */
    public function controlsList($controlsList)
    {
        $controlsList = str_replace('-', ' ', $controlsList);
        $this->attributes['controlsList'] = $controlsList;

        return $this;
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  bool $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, $reset = true)
    {
        $location = $this->url($reset);

        return [
            'name' => 'audio',
            // Escape the URL before it lands in the rawHtml source string: it is
            // emitted verbatim (unlike an image `src`, which goes through an
            // escaped attribute), and the media URL carries an unencoded fragment
            // (urlHash only strips a leading `#`), so a crafted fragment would
            // otherwise break out of src="…" into live markup. (GHSA-6qw9-4vv5-jr97)
            'rawHtml' => '<source src="' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '">Your browser does not support the audio tag.',
            'attributes' => $attributes
        ];
    }
}
