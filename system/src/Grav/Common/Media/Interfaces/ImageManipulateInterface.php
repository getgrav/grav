<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

/**
 * Class implements image manipulation interface.
 */
interface ImageManipulateInterface
{
    /**
     * Allows the ability to override the image's pretty name stored in cache
     *
     * @param string $name
     * @return void
     */
    public function setImagePrettyName(string $name): void;

    /**
     * @return string
     */
    public function getImagePrettyName(): string;

    /**
     * Simply processes with no extra methods.  Useful for triggering events.
     *
     * @return $this
     */
    public function cache();

    /**
     * Generate alternative image widths, using either an array of integers, or
     * a min width, a max width, and a step parameter to fill out the necessary
     * widths. Existing image alternatives won't be overwritten.
     *
     * @param int|int[] $min_width
     * @param int $max_width
     * @param int $step
     * @return $this
     */
    public function derivatives($min_width, int $max_width = 2500, int $step = 200);

    /**
     * Clear out the alternatives.
     *
     * @return void
     */
    public function clearAlternatives(): void;

    /**
     * Sets or gets the quality of the image
     *
     * @param int|null $quality 0-100 quality
     * @return int|$this
     */
    public function quality(int $quality = null);

    /**
     * Sets image output format.
     *
     * @param string $format
     * @return $this
     */
    public function format(string $format);

    /**
     * Set or get sizes parameter for srcset media action
     *
     * @param string|null $sizes
     * @return string|$this
     */
    public function sizes(string $sizes = null);

    /**
     * Allows to set the width attribute from Markdown or Twig
     * Examples: ![Example](myimg.png?width=200&height=400)
     *           ![Example](myimg.png?resize=100,200&width=100&height=200)
     *           ![Example](myimg.png?width=auto&height=auto)
     *           ![Example](myimg.png?width&height)
     *           {{ page.media['myimg.png'].width().height().html }}
     *           {{ page.media['myimg.png'].resize(100,200).width(100).height(200).html }}
     *
     * @param string|int $value A value or 'auto' or empty to use the width of the image
     * @return $this
     */
    public function width($value = 'auto');

    /**
     * Allows to set the height attribute from Markdown or Twig
     * Examples: ![Example](myimg.png?width=200&height=400)
     *           ![Example](myimg.png?resize=100,200&width=100&height=200)
     *           ![Example](myimg.png?width=auto&height=auto)
     *           ![Example](myimg.png?width&height)
     *           {{ page.media['myimg.png'].width().height().html }}
     *           {{ page.media['myimg.png'].resize(100,200).width(100).height(200).html }}
     *
     * @param string|int $value A value or 'auto' or empty to use the height of the image
     * @return $this
     */
    public function height($value = 'auto');

    /* *
     * Filter image by using user defined filter parameters.
     *
     * @param string $filter Filter to be used.
     * @return $this
     */
    public function filter(string $filter = 'image.filters.default');

    /**
     * Return the image higher quality version
     *
     * @return ImageMediaInterface the alternative version with higher quality
     */
    public function higherQualityAlternative(): ImageMediaInterface;
}
