<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

interface RenderableInterface
{
    /**
     * Return HTML markup from the medium.
     *
     * @param string $title
     * @param string $alt
     * @param string $class
     * @param bool $reset
     * @return string
     */
    public function html($title = null, $alt = null, $class = null, $reset = true);

    /**
     * Return Parsedown Element from the medium.
     *
     * @param string $title
     * @param string $alt
     * @param string $class
     * @param string $id
     * @param bool $reset
     * @return string
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $id = null, $reset = true);
}
