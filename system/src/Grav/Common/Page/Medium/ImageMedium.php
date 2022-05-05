<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\ImageManipulateInterface;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Traits\ImageLoadingTrait;
use Grav\Common\Media\Traits\ImageMediaTrait;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function array_key_exists;
use function is_bool;

/**
 * Class ImageMedium
 * @package Grav\Common\Page\Medium
 *
 * @property int $width
 * @property int $height
 * @property int|null $orientation
 */
class ImageMedium extends Medium implements ImageMediaInterface, ImageManipulateInterface
{
    use ImageMediaTrait;
    use ImageLoadingTrait;

    /** @var array */
    protected $defaults = [];
    /** @var array */
    protected $imageSettings = [];
    /** @var string|null */
    private $saved_image_path;

    /**
     * Construct.
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);

        /** @var Config $config */
        $config = $this->getGrav()['config'];

        $this->watermark = $config->get('system.images.watermark.watermark_all', false);
        $this->thumbnailTypes = ['page', 'media', 'default'];
        $this->defaults = [
            'quality' => (int)$config->get('system.images.default_image_quality', 85),
            // CLS configuration
            'auto_sizes' => (bool)$config->get('system.images.cls.auto_sizes', false),
            'aspect_ratio' => (bool)$config->get('system.images.cls.aspect_ratio', false),
            'retina_scale' => (int)$config->get('system.images.cls.retina_scale', 1)
        ];

        $this->def('debug', $config->get('system.images.debug'));

        $path = $this->get('filepath');
        $this->set('thumbnails.media', $path);

        if (!($this->offsetExists('width') && $this->offsetExists('height') && $this->offsetExists('mime'))) {
            user_error(__METHOD__ . '() Creating image without width, height and mime type is deprecated since Grav 1.8', E_USER_DEPRECATED);

            $exists = $path && file_exists($path) && filesize($path);
            if ($exists) {
                $image_info = getimagesize($path);
                if ($image_info) {
                    $this->set('width', $image_info[0]);
                    $this->set('height', $image_info[1]);
                    $this->set('mime', $image_info['mime']);
                }
            }
        }

        if ($config->get('system.images.cache_all', false)) {
            $this->cache();
        }
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
        ] + parent::getMeta();
    }

    /**
     * Reset image.
     *
     * @return $this
     */
    public function reset()
    {
        parent::reset();

        $this->format = 'guess';
        $this->imageSettings = $this->defaults;
        $this->quality = $this->defaults['quality'];

        $this->resetImage();

        return $this;
    }

    /**
     * Return PATH to image.
     *
     * @param bool $reset
     * @return string path to image
     */
    public function path(bool $reset = true): string
    {
        $output = $this->saveImage();

        if ($reset) {
            $this->reset();
        }

        return $output;
    }

    /**
     * Return URL to image.
     *
     * @param bool $reset
     * @return string
     */
    public function url(bool $reset = true): string
    {
        if (!$this->image) {
            return parent::url($reset);
        }

        // FIXME: update this code
        $saved_image_path = $this->saved_image_path = $this->saveImage();

        $output = preg_replace('|^' . preg_quote(GRAV_WEBROOT, '|') . '|', '', $saved_image_path) ?: $saved_image_path;

        $grav = $this->getGrav();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        if ($locator->isStream($output)) {
            $output = (string)($locator->findResource($output, false) ?: $locator->findResource($output, false, true));
        }

        $image_path = (string)($locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true));
        if (Utils::startsWith($output, $image_path)) {
            $image_dir = $locator->findResource('cache://images', false);
            $output = '/' . $image_dir . preg_replace('|^' . preg_quote($image_path, '|') . '|', '', $output);
        }

        if ($reset) {
            $this->reset();
        }

        return trim($grav['base_url'] . '/' . $this->urlQuerystring($output), '\\');
    }

    /**
     * Return srcset string for this Medium and its alternatives.
     *
     * @param bool $reset
     * @return string
     */
    public function srcset(bool $reset = true): string
    {
        if (empty($this->alternatives)) {
            if ($reset) {
                $this->reset();
            }

            return '';
        }

        $srcset = [];
        foreach ($this->alternatives as $medium) {
            $srcset[] = $medium->url($reset) . ' ' . $medium->get('width') . 'w';
        }
        $srcset[] = str_replace(' ', '%20', $this->url($reset)) . ' ' . $this->get('width') . 'w';

        return implode(', ', $srcset);
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  bool $reset
     * @return array
     */
    public function sourceParsedownElement(array $attributes, bool $reset = true): array
    {
        if (empty($attributes['src'])) {
            $attributes['src'] = $this->url(false);
        }

        $srcset = $this->srcset($reset);
        if ($srcset) {
            if (empty($attributes['srcset'])) {
                $attributes['srcset'] = $srcset;
            }
            $attributes['sizes'] = $this->sizes();
        }

        if ($this->saved_image_path && $this->imageSettings['auto_sizes']) {
            // FIXME: we can calculate this from the new image object..?
            if (!array_key_exists('height', $this->attributes) && !array_key_exists('width', $this->attributes)) {
                $info = getimagesize($this->saved_image_path);
                $width = (int)$info[0];
                $height = (int)$info[1];

                $scaling_factor = min(1, $this->imageSettings['retina_scale']);
                $attributes['width'] = (int)($width / $scaling_factor);
                $attributes['height'] = (int)($height / $scaling_factor);

                if ($this->imageSettings['aspect_ratio']) {
                    $style = ($attributes['style'] ?? ' ') . "--aspect-ratio: $width/$height;";
                    $attributes['style'] = trim($style);
                }
            }
        }

        return ['name' => 'img', 'attributes' => $attributes];
    }

    /**
     * Turn the current Medium into a Link
     *
     * @param  bool $reset
     * @param  array  $attributes
     * @return MediaLinkInterface
     */
    public function link(bool $reset = true, array $attributes = []): MediaLinkInterface
    {
        $attributes['href'] = $this->url(false);
        $srcset = $this->srcset(false);
        if ($srcset) {
            $attributes['data-srcset'] = $srcset;
        }

        return parent::link($reset, $attributes);
    }

    /**
     * Turn the current Medium into a Link with lightbox enabled
     *
     * @param  int|null $width
     * @param  int|null $height
     * @param  bool $reset
     * @return MediaLinkInterface
     */
    public function lightbox(int $width = null, int $height = null, bool $reset = true): MediaLinkInterface
    {
        if ($this->mode !== 'source') {
            $this->display('source');
        }

        if ($width && $height) {
            $this->__call('cropResize', [$width, $height]);
        }

        return parent::lightbox($width, $height, $reset);
    }

    /**
     * @param string|bool $enabled
     * @return $this
     */
    public function autoSizes($enabled = true)
    {
        $enabled = is_bool($enabled) ? $enabled : $enabled === 'true';

        $this->imageSettings['auto_sizes'] = $enabled;

        return $this;
    }

    /**
     * @param string|bool $enabled
     * @return $this
     */
    public function aspectRatio($enabled = true)
    {
        $enabled = is_bool($enabled) ? $enabled : $enabled === 'true';

        $this->imageSettings['aspect_ratio'] = $enabled;

        return $this;
    }

    /**
     * @param int $scale
     * @return $this
     */
    public function retinaScale(int $scale = 1)
    {
        $this->imageSettings['retina_scale'] = (int)$scale;

        return $this;
    }
}
