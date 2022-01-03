<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use function array_key_exists;
use function extension_loaded;
use function func_num_args;
use function function_exists;

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

    /** @var int */
    protected $default_quality;

    /** @var bool */
    protected $debug_watermarked = false;

    /** @var bool  */
    protected $auto_sizes;

    /** @var bool */
    protected $aspect_ratio;

    /** @var integer */
    protected $retina_scale;

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
     * Allows the ability to override the image's pretty name stored in cache
     *
     * @param string $name
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
     * Gets medium image, resets image manipulation operations.
     *
     * @return $this
     */
    protected function image()
    {
        $locator = Grav::instance()['locator'];

        // Use existing cache folder or if it doesn't exist, create it.
        $cacheDir = $locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true);

        // Make sure we free previous image.
        unset($this->image);

        /** @var MediaCollectionInterface $media */
        $media = $this->get('media');
        if ($media && method_exists($media, 'getImageFileObject')) {
            $this->image = $media->getImageFileObject($this);
        } else {
            $this->image = ImageFile::open($this->get('filepath'));
        }

        $this->image
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir)
            ->setPrettyName($this->getImagePrettyName());

        // Fix orientation if enabled
        $config = Grav::instance()['config'];
        if ($config->get('system.images.auto_fix_orientation', false) &&
            extension_loaded('exif') && function_exists('exif_read_data')) {
            $this->image->fixOrientation();
        }

        // Set CLS configuration
        $this->auto_sizes = $config->get('system.images.cls.auto_sizes', false);
        $this->aspect_ratio = $config->get('system.images.cls.aspect_ratio', false);
        $this->retina_scale = $config->get('system.images.cls.retina_scale', 1);

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
