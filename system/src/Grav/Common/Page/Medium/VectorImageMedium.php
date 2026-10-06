<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
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
    public function __construct($items = [], ?Blueprint $blueprint = null)
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

        // GHSA-3446-6mgw-f79p: strip DOCTYPE/ENTITY declarations and pass
        // LIBXML_NONET to prevent XXE / billion-laughs / network exfiltration
        // when reading width/height from an attacker-supplied SVG.
        $svg = (string) file_get_contents($path);
        $svg = preg_replace('/<!DOCTYPE\b[^>]*(?:\[[^\]]*\])?[^>]*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<!ENTITY\b[^>]*>/i', '', $svg) ?? $svg;

        $previousEntityLoader = null;
        if (\PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            $previousEntityLoader = libxml_disable_entity_loader(true);
        }
        try {
            $xml = simplexml_load_string($svg, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            if ($previousEntityLoader !== null) {
                libxml_disable_entity_loader($previousEntityLoader);
            }
        }
        $attr = $xml ? $xml->attributes() : null;
        if (!$attr instanceof \SimpleXMLElement) {
            return;
        }

        // Get the size from svg image. The width/height attributes only count when both are
        // plain pixel lengths: "100%", "2em" or "10cm" are not pixel sizes, so the viewBox is
        // used for those instead.
        $width = static::parsePixelLength((string)$attr->width);
        $height = static::parsePixelLength((string)$attr->height);

        if (!$width || !$height) {
            $width = $height = null;

            // The viewBox is "min-x min-y width height", separated by whitespace and/or a comma.
            $size = preg_split('/[\s,]+/', trim((string)$attr->viewBox)) ?: [];
            if (\count($size) === 4) {
                $width = static::parsePixelLength($size[2]);
                $height = static::parsePixelLength($size[3]);
            }
        }

        if ($width && $height) {
            $this->def('width', $width);
            $this->def('height', $height);
        }
    }

    /**
     * Read a plain number or a `px` length as whole pixels. Returns null for anything else,
     * including percentages, relative or physical units, and sizes that are not above zero.
     *
     * @param string $value
     * @return int|null
     */
    protected static function parsePixelLength(string $value): ?int
    {
        if (!preg_match('/^\s*(\d+(?:\.\d+)?|\.\d+)(?:px)?\s*$/i', $value, $matches)) {
            return null;
        }

        return (int)$matches[1] ?: null;
    }
}
