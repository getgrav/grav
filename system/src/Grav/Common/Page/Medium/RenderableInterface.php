<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

/**
 * Interface RenderableInterface
 * @package Grav\Common\Page\Medium
 */
interface RenderableInterface
{
    /**
     * Return HTML markup from the medium.
     *
     * @param string|null $title
     * @param string|null $alt
     * @param string|null $class
     * @param string|null $id
     * @param bool $reset
     * @return string
     */
    public function html(string $title = null, string $alt = null, string $class = null, string $id = null, bool $reset = true): string;

    /**
     * Return Parsedown Element from the medium.
     *
     * @param string|null $title
     * @param string|null $alt
     * @param string|null $class
     * @param string|null $id
     * @param bool $reset
     * @return array
     */
    public function parsedownElement(string $title = null, string $alt = null, string $class = null, string $id = null, bool $reset = true): array;
}
