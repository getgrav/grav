<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\MediaFileInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Traits\MediaFileTrait;
use Grav\Common\Media\Traits\MediaObjectTrait;

/**
 * Class Medium
 * @package Grav\Common\Page\Medium
 *
 * @property string $filepath
 * @property string $filename
 * @property string $basename
 * @property string $mime
 * @property int $size
 * @property int $modified
 * @property array $metadata
 * @property int|string $timestamp
 */
class Medium extends Data implements RenderableInterface, MediaFileInterface
{
    use MediaObjectTrait;
    use MediaFileTrait;
    use ParsedownHtmlTrait;

    /**
     * Media actions that may be invoked from an editor-authored Markdown image
     * URL (e.g. `![x](img.png?resize=100,200&grayscale&class=foo)`) or a media
     * URL request. Excerpts::processMediaActions() and the Flex media response
     * builder call these by method name. The gate blocks any *real* public
     * method that is not on this allowlist, so untrusted page content can't reach
     * arbitrary methods on a medium (the enabler behind GHSA-ffmg-hfvg-jhg9 and
     * the earlier style/attribute advisories). Names that aren't real methods
     * still fall through to the medium's __call() handler, which only appends
     * them to the image URL's querystring (where image filters like `grayscale`
     * are applied during image generation, and unknown params are reflected,
     * escaped, into the `src`); that path invokes no code, so it stays open.
     *
     * This list mirrors the actions documented at
     * https://learn.getgrav.org/content/media.
     *
     * Matching is case-insensitive (see {@see isAllowedAction()}) because PHP
     * method dispatch is, so each action is listed once in its documented
     * casing. This list is intentionally not configurable; add newly documented
     * actions here in core as needed.
     *
     * @var string[]
     */
    public const ALLOWED_ACTIONS = [
        // Image manipulation (cf. ImageMediaTrait::$magic_actions). zoomCrop and
        // its legacy alias cropZoom are both kept for backwards compatibility.
        'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop', 'cropZoom',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss', 'smooth',
        'sharp', 'edge', 'colorize', 'sepia', 'enableProgressive', 'rotate',
        'flip', 'fixOrientation', 'gaussianBlur', 'format', 'quality',
        'watermark', 'derivatives', 'cache',
        // Rendering, layout, and HTML attributes
        'lightbox', 'link', 'classes', 'style', 'id', 'attribute',
        'width', 'height', 'sizes', 'autoSizes', 'aspectRatio', 'retinaScale',
        'loading', 'decoding', 'fetchpriority', 'display', 'thumbnail', 'reset',
        // Audio / video players
        'controls', 'controlsList', 'loop', 'autoplay', 'muted', 'preload',
        'playsinline', 'poster',
    ];

    /** @var array<string,true>|null Memoized lower-cased ALLOWED_ACTIONS lookup. */
    private static $allowedActionLookup = null;

    /**
     * Whether a media action may be invoked from an editor-authored URL.
     * Case-insensitive, matching PHP's method-name dispatch so that e.g.
     * `?Resize=` cannot slip past the allowlist while still calling resize().
     *
     * @param string $method
     * @return bool
     */
    public static function isAllowedAction(string $method): bool
    {
        if (self::$allowedActionLookup === null) {
            self::$allowedActionLookup = array_fill_keys(
                array_map('strtolower', self::ALLOWED_ACTIONS),
                true
            );
        }

        return isset(self::$allowedActionLookup[strtolower($method)]);
    }

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint|null $blueprint
     */
    public function __construct($items = [], ?Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        if (Grav::instance()['config']->get('system.media.enable_media_timestamp', true)) {
            $this->timestamp = Grav::instance()['cache']->getKey();
        }

        $this->def('mime', 'application/octet-stream');

        if (!$this->offsetExists('size')) {
            $path = $this->get('filepath');
            $this->def('size', filesize($path));
        }

        $this->reset();
    }

    /**
     * Clone medium.
     */
    #[\ReturnTypeWillChange]
    public function __clone()
    {
        // Allows future compatibility as parent::__clone() works.
    }

    /**
     * Add meta file for the medium.
     *
     * @param string $filepath
     */
    public function addMetaFile($filepath)
    {
        $this->metadata = (array)CompiledYamlFile::instance($filepath)->content();
        $this->merge($this->metadata);
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return [
            'mime' => $this->mime,
            'size' => $this->size,
            'modified' => $this->modified,
        ];
    }

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString(): string
    {
        return $this->html();
    }

    /**
     * @param string $thumb
     * @return Medium|null
     */
    protected function createThumbnail($thumb)
    {
        return MediumFactory::fromFile($thumb, ['type' => 'thumbnail']);
    }

    /**
     * @param array $attributes
     * @return MediaLinkInterface
     */
    protected function createLink(array $attributes)
    {
        return new Link($attributes, $this);
    }

    /**
     * @return Grav
     */
    protected function getGrav(): Grav
    {
        return Grav::instance();
    }

    /**
     * @return array
     */
    protected function getItems(): array
    {
        return $this->items;
    }
}
