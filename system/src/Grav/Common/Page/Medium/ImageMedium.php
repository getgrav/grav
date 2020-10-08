<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use BadFunctionCallException;
use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\ImageManipulateInterface;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Traits\ImageLoadingTrait;
use Grav\Common\Media\Traits\ImageMediaTrait;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function func_get_args;
use function in_array;

/**
 * Class ImageMedium
 * @package Grav\Common\Page\Medium
 */
class ImageMedium extends Medium implements ImageMediaInterface, ImageManipulateInterface
{
    use ImageMediaTrait;
    use ImageLoadingTrait;

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint|null $blueprint
     */
    public function __construct($items = [], Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        $config = $this->getGrav()['config'];

        $this->thumbnailTypes = ['page', 'media', 'default'];
        $this->default_quality = $config->get('system.images.default_image_quality', 85);
        $this->def('debug', $config->get('system.images.debug'));

        $path = $this->get('filepath');
        if (!$path || !file_exists($path) || !filesize($path)) {
            return;
        }

        $this->set('thumbnails.media', $path);

        if (!($this->offsetExists('width') && $this->offsetExists('height') && $this->offsetExists('mime'))) {
            $image_info = getimagesize($path);
            if ($image_info) {
                $this->def('width', $image_info[0]);
                $this->def('height', $image_info[1]);
                $this->def('mime', $image_info['mime']);
            }
        }

        $this->reset();

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
        ] + parent::getMeta();
    }

    /**
     * Also unset the image on destruct.
     */
    public function __destruct()
    {
        unset($this->image);
    }

    /**
     * Also clone image.
     */
    public function __clone()
    {
        if ($this->image) {
            $this->image = clone $this->image;
        }

        parent::__clone();
    }

    /**
     * Reset image.
     *
     * @return $this
     */
    public function reset()
    {
        parent::reset();

        if ($this->image) {
            $this->image();
            $this->medium_querystring = [];
            $this->filter();
            $this->clearAlternatives();
        }

        $this->format = 'guess';
        $this->quality = $this->default_quality;

        $this->debug_watermarked = false;

        return $this;
    }

    /**
     * Add meta file for the medium.
     *
     * @param string $filepath
     * @return $this
     */
    public function addMetaFile($filepath)
    {
        parent::addMetaFile($filepath);

        // Apply filters in meta file
        $this->reset();

        return $this;
    }

    /**
     * Return PATH to image.
     *
     * @param bool $reset
     * @return string path to image
     */
    public function path($reset = true)
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
    public function url($reset = true)
    {
        $grav = $this->getGrav();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $image_path = (string)($locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true));
        $saved_image_path = $this->saveImage();

        $output = preg_replace('|^' . preg_quote(GRAV_ROOT, '|') . '|', '', $saved_image_path) ?: $saved_image_path;

        if ($locator->isStream($output)) {
            $output = (string)($locator->findResource($output, false) ?: $locator->findResource($output, false, true));
        }

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
    public function srcset($reset = true)
    {
        if (empty($this->alternatives)) {
            if ($reset) {
                $this->reset();
            }

            return '';
        }

        $srcset = [];
        foreach ($this->alternatives as $ratio => $medium) {
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
    public function sourceParsedownElement(array $attributes, $reset = true)
    {
        empty($attributes['src']) && $attributes['src'] = $this->url(false);

        $srcset = $this->srcset($reset);
        if ($srcset) {
            empty($attributes['srcset']) && $attributes['srcset'] = $srcset;
            $attributes['sizes'] = $this->sizes();
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
    public function link($reset = true, array $attributes = [])
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
     * @param  int  $width
     * @param  int  $height
     * @param  bool $reset
     * @return MediaLinkInterface
     */
    public function lightbox($width = null, $height = null, $reset = true)
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
     * Handle this commonly used variant
     *
     * @return $this
     */
    public function cropZoom()
    {
        $this->__call('zoomCrop', func_get_args());

        return $this;
    }

    /**
     * Forward the call to the image processing method.
     *
     * @param string $method
     * @param mixed $args
     * @return $this|mixed
     */
    public function __call($method, $args)
    {
        if (!in_array($method, static::$magic_actions, true)) {
            return parent::__call($method, $args);
        }

        // Always initialize image.
        if (!$this->image) {
            $this->image();
        }

        try {
            $this->image->{$method}(...$args);

            /** @var ImageMediaInterface $medium */
            foreach ($this->alternatives as $medium) {
                $args_copy = $args;

                // regular image: resize 400x400 -> 200x200
                // --> @2x: resize 800x800->400x400
                if (isset(static::$magic_resize_actions[$method])) {
                    foreach (static::$magic_resize_actions[$method] as $param) {
                        if (isset($args_copy[$param])) {
                            $args_copy[$param] *= $medium->get('ratio');
                        }
                    }
                }

                // Do the same call for alternative media.
                $medium->__call($method, $args_copy);
            }
        } catch (BadFunctionCallException $e) {
        }

        return $this;
    }
}
