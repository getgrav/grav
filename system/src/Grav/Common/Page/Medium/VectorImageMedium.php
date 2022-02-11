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

        $height = false;
        $width = false;

        if (!extension_loaded('simplexml')) {
            return;
        }

        $path = $this->get('filepath');
        if (!$path || !file_exists($path) || !filesize($path)) {
            return;
        }

        $xml = simplexml_load_string(file_get_contents($path));
        $attr = $xml->attributes();

        if (!$attr instanceof \SimpleXMLElement) {
            return;
        }

        if ($attr->width > 0 && $attr->height > 0) {
            $width = (int)$attr->width;
            $height = (int)$attr->height;
        } elseif ($attr->viewBox && count($size = explode(' ', $attr->viewBox)) === 4) {
            $width = (int)$size[2];
            $height = (int)$size[3];
        }

        if ($width && $height) {
            $this->def('width', $width);
            $this->def('height', $height);
        }
    }
}
