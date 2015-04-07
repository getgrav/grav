<?php
namespace Grav\Common\Page\Medium;

/**
 * Renderable Medium objects can be rendered to HTML markup and Parsedown objects
 *
 * @author Grav
 * @license MIT
 *
 */
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
     * @param bool $reset
     * @return string
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true);
}
