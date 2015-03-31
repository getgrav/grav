<?php
namespace Grav\Common\Page\Medium;

/**
 * The Image medium holds information related to an individual image. These are then stored in the Media object.
 *
 * @author Grav
 * @license MIT
 *
 */
class StaticImageMedium extends Medium
{
    use StaticResizeTrait;

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  boolean $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, $reset = true)
    {
        empty($attributes['src']) && $attributes['src'] = $this->url($reset);

        return [ 'name' => 'image', 'attributes' => $attributes ];
    }
}
