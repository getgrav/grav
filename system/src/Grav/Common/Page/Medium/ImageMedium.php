<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
use Gregwar\Image\Image;
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
     * @var mixed|string
     */
    private $saved_image_path;

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
    #[\ReturnTypeWillChange]
    public function __destruct()
    {
        unset($this->image);
    }

    /**
     * Also clone image.
     */
    #[\ReturnTypeWillChange]
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

        $config = $this->getGrav()['config'];
        // Set CLS configuration
        $this->auto_sizes = $config->get('system.images.cls.auto_sizes', false);
        $this->aspect_ratio = $config->get('system.images.cls.aspect_ratio', false);
        $this->retina_scale = $config->get('system.images.cls.retina_scale', 1);

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
        $saved_image_path = $this->saved_image_path = $this->saveImage();

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

        if ($this->saved_image_path && $this->auto_sizes) {
            if (!array_key_exists('height', $this->attributes) && !array_key_exists('width', $this->attributes)) {
                $info = getimagesize($this->saved_image_path);
                $width = (int)$info[0];
                $height = (int)$info[1];

                $scaling_factor = $this->retina_scale > 0 ? $this->retina_scale : 1;
                $attributes['width'] = (int)($width / $scaling_factor);
                $attributes['height'] = (int)($height / $scaling_factor);

                if ($this->aspect_ratio) {
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
     * @param string $enabled
     * @return $this
     */
    public function autoSizes($enabled = 'true')
    {
        $this->auto_sizes = $enabled === 'true' ?: false;

        return $this;
    }

    /**
     * @param string $enabled
     * @return $this
     */
    public function aspectRatio($enabled = 'true')
    {
        $this->aspect_ratio = $enabled === 'true' ?: false;

        return $this;
    }

    /**
     * @param int $scale
     * @return $this
     */
    public function retinaScale($scale = 1)
    {
        $this->retina_scale = (int)$scale;

        return $this;
    }

    /**
     * @param string|null $image
     * @param string|null $position
     * @param int|float|null $scale
     * @return $this
     */
    public function watermark($image = null, $position = null, $scale = null)
    {
        $grav = $this->getGrav();

        $locator = $grav['locator'];
        $config = $grav['config'];

        $args = func_get_args();

        $file = $args[0] ?? '1'; // using '1' because of markdown. doing ![](image.jpg?watermark) returns $args[0]='1';
        $file = $file === '1' ? $config->get('system.images.watermark.image') : $args[0];

        $watermark = $locator->findResource($file);
        $watermark = ImageFile::open($watermark);

        // Scaling operations
        $scale     = ($scale ?? $config->get('system.images.watermark.scale', 100)) / 100;
        $wwidth    = $this->get('width')  * $scale;
        $wheight   = $this->get('height') * $scale;
        $watermark->resize($wwidth, $wheight);

        // Position operations
        $position = !empty($args[1]) ? explode('-',  $args[1]) : ['center', 'center']; // todo change to config
        $positionY = $position[0] ?? $config->get('system.images.watermark.position_y', 'center');
        $positionX = $position[1] ?? $config->get('system.images.watermark.position_x', 'center');

        switch ($positionY)
        {
            case 'top':
                $positionY = 0;
                break;

            case 'bottom':
                $positionY = $this->get('height')-$wheight;
                break;

            case 'center':
                $positionY = ($this->get('height')/2) - ($wheight/2);
                break;
        }

        switch ($positionX)
        {
            case 'left':
                $positionX = 0;
                break;

            case 'right':
                $positionX = $this->get('width')-$wwidth;
                break;

            case 'center':
                $positionX = ($this->get('width')/2) - ($wwidth/2);
                break;
        }

        $this->__call('merge', [$watermark,$positionX, $positionY]);

        return $this;
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
     * Add a frame to image
     *
     * @return $this
     */
    public function addFrame(int $border = 10, string $color = '0x000000')
    {
      if($border > 0 && preg_match('/^0x[a-f0-9]{6}$/i', $color)) { // $border must be an integer and bigger than 0; $color must be formatted as an HEX value (0x??????).
        $image = ImageFile::open($this->path());
      }
      else {
        return $this;
      }

      $dst_width = $image->width()+2*$border;
      $dst_height = $image->height()+2*$border;

      $frame = ImageFile::create($dst_width, $dst_height);

      $frame->__call('fill', [$color]);

      $this->image = $frame;

      $this->__call('merge', [$image, $border, $border]);

      $this->saveImage();

      return $this;

    }

    /**
     * Forward the call to the image processing method.
     *
     * @param string $method
     * @param mixed $args
     * @return $this|mixed
     */
    #[\ReturnTypeWillChange]
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
