<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Data\Blueprint;


/**
 * Class StaticImageMedium
 * @package Grav\Common\Page\Medium
 */
class VectorImageMedium extends StaticImageMedium
{
    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint|null $blueprint
     */
    public function __construct($items = [], Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        // If we already have the image size, we do not need to do anything else.
        $width = $this->get('width');
        $height = $this->get('height');
        if ($width && $height) {
            return;
        }

        // Make sure that getting image size is supported.
        if ($this->mime !== 'image/svg+xml' || !\extension_loaded('simplexml')) {
            return;
        }

        // Make sure that the image exists.
        $path = $this->get('filepath');
        if (!$path || !file_exists($path) || !filesize($path)) {
            return;
        }

        $xml = simplexml_load_string(file_get_contents($path));
        $attr = $xml ? $xml->attributes() : null;
        if (!$attr instanceof \SimpleXMLElement) {
            return;
        }

        // Get the size from svg image.
        if ($attr->width && $attr->height) {
            $width = (string)$attr->width;
            $height = (string)$attr->height;
        } elseif ($attr->viewBox && \count($size = explode(' ', (string)$attr->viewBox)) === 4) {
            [,$width,$height,] = $size;
        }

        if ($width && $height) {
            $this->def('width', (int)$width);
            $this->def('height', (int)$height);
        }
    }
}
