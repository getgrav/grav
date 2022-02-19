<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use BadFunctionCallException;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function array_key_exists;
use function extension_loaded;
use function func_num_args;
use function function_exists;
use function in_array;

/**
 * Trait ImageMediaTrait
 * @package Grav\Common\Media\Traits
 */
trait ImageMediaTrait
{
    /** @var ImageFile|null */
    protected $image;
    /** @var string */
    protected $format = 'guess';
    /** @var int */
    protected $quality;
    /** @var bool */
    protected $debug_watermarked = false;
    /** @var bool */
    protected $watermark;

    /** @var array */
    public static $magic_actions = [
        'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss',
        'smooth', 'sharp', 'edge', 'colorize', 'sepia', 'enableProgressive',
        'rotate', 'flip', 'fixOrientation', 'gaussianBlur', 'format', 'create', 'fill', 'merge'
    ];

    /** @var array */
    public static $magic_resize_actions = [
        'resize' => [0, 1],
        'forceResize' => [0, 1],
        'cropResize' => [0, 1],
        'crop' => [0, 1, 2, 3],
        'zoomCrop' => [0, 1]
    ];

    /** @var string */
    protected $sizes = '100vw';

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
     * @return void
     */
    protected function resetImage(): void
    {
        if ($this->image) {
            $this->image();
            $this->filter();
            $this->clearAlternatives();
        }
    }

    /**
     * Allows the ability to override the image's pretty name stored in cache
     *
     * @param string $name
     * @return void
     */
    public function setImagePrettyName($name)
    {
        $this->set('prettyname', $name);
        if ($this->image) {
            $this->image->setPrettyName($name);
        }
    }

    /**
     * @return string
     */
    public function getImagePrettyName()
    {
        if ($this->get('prettyname')) {
            return $this->get('prettyname');
        }

        $basename = $this->get('basename');
        if (preg_match('/[a-z0-9]{40}-(.*)/', $basename, $matches)) {
            $basename = $matches[1];
        }

        return $basename;
    }

    /**
     * Simply processes with no extra methods.  Useful for triggering events.
     *
     * @return $this
     */
    public function cache()
    {
        if (!$this->image) {
            $this->image();
        }

        return $this;
    }

    /**
     * Generate alternative image widths, using either an array of integers, or
     * a min width, a max width, and a step parameter to fill out the necessary
     * widths. Existing image alternatives won't be overwritten.
     *
     * @param  int|int[] $min_width
     * @param  int       $max_width
     * @param  int       $step
     * @return $this
     */
    public function derivatives($min_width, $max_width = 2500, $step = 200)
    {
        if (!empty($this->alternatives)) {
            $max = max(array_keys($this->alternatives));
            $base = $this->alternatives[$max];
        } else {
            $base = $this;
        }

        $widths = [];

        if (func_num_args() === 1) {
            foreach ((array) func_get_arg(0) as $width) {
                if ($width < $base->get('width')) {
                    $widths[] = $width;
                }
            }
        } else {
            $max_width = min($max_width, $base->get('width'));

            for ($width = $min_width; $width < $max_width; $width += $step) {
                $widths[] = $width;
            }
        }

        foreach ($widths as $width) {
            // Only generate image alternatives that don't already exist
            if (array_key_exists((int) $width, $this->alternatives)) {
                continue;
            }

            $derivative = MediumFactory::fromFile($base->get('filepath'));

            // It's possible that MediumFactory::fromFile returns null if the
            // original image file no longer exists and this class instance was
            // retrieved from the page cache
            if (null !== $derivative) {
                $index = 2;
                $alt_widths = array_keys($this->alternatives);
                sort($alt_widths);

                foreach ($alt_widths as $i => $key) {
                    if ($width > $key) {
                        $index += max($i, 1);
                    }
                }

                $basename = preg_replace('/(@\d+x)?$/', "@{$width}w", $base->get('basename'), 1);
                $derivative->setImagePrettyName($basename);

                $ratio = $base->get('width') / $width;
                $height = $derivative->get('height') / $ratio;

                $derivative->resize($width, $height);
                $derivative->set('width', $width);
                $derivative->set('height', $height);

                $this->addAlternative($ratio, $derivative);
            }
        }

        return $this;
    }

    /**
     * Clear out the alternatives.
     *
     * @return void
     */
    public function clearAlternatives()
    {
        $this->alternatives = [];
    }

    /**
     * Sets or gets the quality of the image
     *
     * @param  int|null $quality 0-100 quality
     * @return int|$this
     */
    public function quality($quality = null)
    {
        if ($quality) {
            if (!$this->image) {
                $this->image();
            }

            $this->quality = $quality;

            return $this;
        }

        return $this->quality;
    }

    /**
     * Sets image output format.
     *
     * @param string $format
     * @return $this
     */
    public function format($format)
    {
        if (!$this->image) {
            $this->image();
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Set or get sizes parameter for srcset media action
     *
     * @param  string|null $sizes
     * @return string
     */
    public function sizes($sizes = null)
    {
        if ($sizes) {
            $this->sizes = $sizes;

            return $this;
        }

        return empty($this->sizes) ? '100vw' : $this->sizes;
    }

    /**
     * Allows to set the width attribute from Markdown or Twig
     * Examples: ![Example](myimg.png?width=200&height=400)
     *           ![Example](myimg.png?resize=100,200&width=100&height=200)
     *           ![Example](myimg.png?width=auto&height=auto)
     *           ![Example](myimg.png?width&height)
     *           {{ page.media['myimg.png'].width().height().html }}
     *           {{ page.media['myimg.png'].resize(100,200).width(100).height(200).html }}
     *
     * @param string|int $value A value or 'auto' or empty to use the width of the image
     * @return $this
     */
    public function width($value = 'auto')
    {
        if (!$value || $value === 'auto') {
            $this->attributes['width'] = $this->get('width');
        } else {
            $this->attributes['width'] = $value;
        }

        return $this;
    }

    /**
     * Allows to set the height attribute from Markdown or Twig
     * Examples: ![Example](myimg.png?width=200&height=400)
     *           ![Example](myimg.png?resize=100,200&width=100&height=200)
     *           ![Example](myimg.png?width=auto&height=auto)
     *           ![Example](myimg.png?width&height)
     *           {{ page.media['myimg.png'].width().height().html }}
     *           {{ page.media['myimg.png'].resize(100,200).width(100).height(200).html }}
     *
     * @param string|int $value A value or 'auto' or empty to use the height of the image
     * @return $this
     */
    public function height($value = 'auto')
    {
        if (!$value || $value === 'auto') {
            $this->attributes['height'] = $this->get('height');
        } else {
            $this->attributes['height'] = $value;
        }

        return $this;
    }

    /**
     * Filter image by using user defined filter parameters.
     *
     * @param string $filter Filter to be used.
     * @return $this
     */
    public function filter($filter = 'image.filters.default')
    {
        $filters = (array) $this->get($filter, []);
        foreach ($filters as $params) {
            $params = (array) $params;
            $method = array_shift($params);
            $this->__call($method, $params);
        }

        return $this;
    }

    /**
     * Return the image higher quality version
     *
     * @return ImageMediaInterface|$this the alternative version with higher quality
     */
    public function higherQualityAlternative()
    {
        if ($this->alternatives) {
            /** @var ImageMedium $max */
            $max = reset($this->alternatives);
            /** @var ImageMedium $alternative */
            foreach ($this->alternatives as $alternative) {
                if ($alternative->quality() > $max->quality()) {
                    $max = $alternative;
                }
            }

            return $max;
        }

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
     * Add a frame to image
     *
     * @return $this
     */
    public function addFrame(int $border = 10, string $color = '0x000000')
    {
        if($border > 0 && preg_match('/^0x[a-f0-9]{6}$/i', $color)) { // $border must be an integer and bigger than 0; $color must be formatted as an HEX value (0x??????).
            $image = ImageFile::fromData($this->readFile());
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
     * @return mixed
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

    /**
     * Gets medium image, resets image manipulation operations.
     *
     * @return $this
     */
    protected function image()
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        // Use existing cache folder or if it doesn't exist, create it.
        $cacheDir = $locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true);

        // Make sure we free previous image.
        unset($this->image);

        $this->image = ImageFile::fromData($this->readFile());
        $this->image
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir)
            ->setPrettyName($this->getImagePrettyName());

        // Fix orientation if enabled
        /** @var Config $config */
        $config = Grav::instance()['config'];
        if ($config->get('system.images.auto_fix_orientation', false) &&
            extension_loaded('exif') && function_exists('exif_read_data')) {
            $this->image->fixOrientation();
        }

        $this->watermark = $config->get('system.images.watermark.watermark_all', false);

        return $this;
    }

    /**
     * Save the image with cache.
     *
     * @return string
     */
    protected function saveImage()
    {
        if (!$this->image) {
            return parent::path(false);
        }

        $this->filter();

        if (isset($this->result)) {
            return $this->result;
        }

        if ($this->format === 'guess') {
            $extension = strtolower($this->get('extension'));
            $this->format($extension);
        }

        if (!$this->debug_watermarked && $this->get('debug')) {
            $ratio = $this->get('ratio');
            if (!$ratio) {
                $ratio = 1;
            }

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $overlay = $locator->findResource("system://assets/responsive-overlays/{$ratio}x.png") ?: $locator->findResource('system://assets/responsive-overlays/unknown.png');
            $this->image->merge(ImageFile::open($overlay));
        }

        if ($this->watermark) {
            $this->watermark();
        }

        return $this->image->cacheFile($this->format, $this->quality, false, [$this->get('width'), $this->get('height'), $this->get('modified')]);
    }
}
