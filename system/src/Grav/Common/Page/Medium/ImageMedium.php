<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Utils;

class ImageMedium extends Medium
{
    /**
     * @var array
     */
    protected $thumbnailTypes = ['page', 'media', 'default'];

    /**
     * @var ImageFile
     */
    protected $image;

    /**
     * @var string
     */
    protected $format = 'guess';

    /**
     * @var int
     */
    protected $quality;

    /**
     * @var int
     */
    protected $default_quality;

    /**
     * @var boolean
     */
    protected $debug_watermarked = false;

    /**
     * @var array
     */
    public static $magic_actions = [
        'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss',
        'smooth', 'sharp', 'edge', 'colorize', 'sepia', 'enableProgressive',
        'rotate', 'flip', 'fixOrientation', 'gaussianBlur'
    ];

    /**
     * @var array
     */
    public static $magic_resize_actions = [
        'resize' => [0, 1],
        'forceResize' => [0, 1],
        'cropResize' => [0, 1],
        'crop' => [0, 1, 2, 3],
        'zoomCrop' => [0, 1]
    ];

    /**
     * @var string
     */
    protected $sizes = '100vw';

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = [], Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        $config = Grav::instance()['config'];

        if (filesize($this->get('filepath')) === 0) {
            return;
        }

        $image_info = getimagesize($this->get('filepath'));
        $this->def('width', $image_info[0]);
        $this->def('height', $image_info[1]);
        $this->def('mime', $image_info['mime']);
        $this->def('debug', $config->get('system.images.debug'));

        $this->set('thumbnails.media', $this->get('filepath'));

        $this->default_quality = $config->get('system.images.default_image_quality', 85);

        $this->reset();

        if ($config->get('system.images.cache_all', false)) {
            $this->cache();
        }
    }

    /**
     * Add meta file for the medium.
     *
     * @param $filepath
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
     * Clear out the alternatives
     */
    public function clearAlternatives()
    {
        $this->alternatives = [];
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
        $image_path = Grav::instance()['locator']->findResource('cache://images', true);
        $image_dir = Grav::instance()['locator']->findResource('cache://images', false);
        $saved_image_path = $this->saveImage();

        $output = preg_replace('|^' . preg_quote(GRAV_ROOT) . '|', '', $saved_image_path);

        if (Utils::startsWith($output, $image_path)) {
            $output = '/' . $image_dir . preg_replace('|^' . preg_quote($image_path) . '|', '', $output);
        }

        if ($reset) {
            $this->reset();
        }

        return trim(Grav::instance()['base_url'] . '/' . ltrim($output . $this->querystring() . $this->urlHash(), '/'), '\\');
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
        $srcset[] = $this->url($reset) . ' ' . $this->get('width') . 'w';

        return implode(', ', $srcset);
    }

    /**
     * Allows the ability to override the Inmage's Pretty name stored in cache
     *
     * @param $name
     */
    public function setImagePrettyName($name)
    {
        $this->set('prettyname', $name);
        if ($this->image) {
            $this->image->setPrettyName($name);
        }
    }

    public function getImagePrettyName()
    {
        if ($this->get('prettyname')) {
            return $this->get('prettyname');
        } else {
            $basename = $this->get('basename');
            if (preg_match('/[a-z0-9]{40}-(.*)/', $basename, $matches)) {
                $basename = $matches[1];
            }
            return $basename;
        }
    }

    /**
     * Generate alternative image widths, using either an array of integers, or
     * a min width, a max width, and a step parameter to fill out the necessary
     * widths. Existing image alternatives won't be overwritten.
     *
     * @param  int|int[] $min_width
     * @param  int       [$max_width=2500]
     * @param  int       [$step=200]
     * @return $this
     */
    public function derivatives($min_width, $max_width = 2500, $step = 200) {
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

            for ($width = $min_width; $width < $max_width; $width = $width + $step) {
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
            if (isset($derivative)) {
                $index = 2;
                $alt_widths = array_keys($this->alternatives);
                sort($alt_widths);

                foreach ($alt_widths as $i => $key) {
                    if ($width > $key) {
                        $index += max($i, 1);
                    }
                }

                $basename = preg_replace('/(@\d+x){0,1}$/', "@{$width}w", $base->get('basename'), 1);
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
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  boolean $reset
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

        return [ 'name' => 'img', 'attributes' => $attributes ];
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
            $this->image->clearOperations(); // Clear previously applied operations
            $this->querystring('');
            $this->filter();
            $this->clearAlternatives();
        }

        $this->format = 'guess';
        $this->quality = $this->default_quality;

        $this->debug_watermarked = false;

        return $this;
    }

    /**
     * Turn the current Medium into a Link
     *
     * @param  boolean $reset
     * @param  array  $attributes
     * @return Link
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
     * @param  boolean $reset
     * @return Link
     */
    public function lightbox($width = null, $height = null, $reset = true)
    {
        if ($this->mode !== 'source') {
            $this->display('source');
        }

        if ($width && $height) {
            $this->cropResize($width, $height);
        }

        return parent::lightbox($width, $height, $reset);
    }

    /**
     * Sets or gets the quality of the image
     *
     * @param  int $quality 0-100 quality
     * @return Medium
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
     * @param  string $sizes
     * @return $this
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
     * @param mixed $value A value or 'auto' or empty to use the width of the image
     * @return $this
     */
    public function width($value = 'auto')
    {
        if (!$value || $value == 'auto')
            $this->attributes['width'] = $this->get('width');
        else
            $this->attributes['width'] = $value;
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
     * @param mixed $value A value or 'auto' or empty to use the height of the image
     * @return $this
     */
    public function height($value = 'auto')
    {
        if (!$value || $value == 'auto')
            $this->attributes['height'] = $this->get('height');
        else
            $this->attributes['height'] = $value;
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
        if ($method == 'cropZoom') {
            $method = 'zoomCrop';
        }

        if (!in_array($method, self::$magic_actions)) {
            return parent::__call($method, $args);
        }

        // Always initialize image.
        if (!$this->image) {
            $this->image();
        }

        try {
            call_user_func_array([$this->image, $method], $args);

            foreach ($this->alternatives as $medium) {
                if (!$medium->image) {
                    $medium->image();
                }

                $args_copy = $args;

                // regular image: resize 400x400 -> 200x200
                // --> @2x: resize 800x800->400x400
                if (isset(self::$magic_resize_actions[$method])) {
                    foreach (self::$magic_resize_actions[$method] as $param) {
                        if (isset($args_copy[$param])) {
                            $args_copy[$param] *= $medium->get('ratio');
                        }
                    }
                }

                call_user_func_array([$medium, $method], $args_copy);
            }
        } catch (\BadFunctionCallException $e) {
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

        $file = $this->get('filepath');

        // Use existing cache folder or if it doesn't exist, create it.
        $cacheDir = $locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true);

        $this->image = ImageFile::open($file)
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir)
            ->setPrettyName($this->getImagePrettyName());

        return $this;
    }

    /**
     * Save the image with cache.
     *
     * @return mixed|string
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

        if ($this->get('debug') && !$this->debug_watermarked) {
            $ratio = $this->get('ratio');
            if (!$ratio) {
                $ratio = 1;
            }

            $locator = Grav::instance()['locator'];
            $overlay = $locator->findResource("system://assets/responsive-overlays/{$ratio}x.png") ?: $locator->findResource('system://assets/responsive-overlays/unknown.png');
            $this->image->merge(ImageFile::open($overlay));
        }

        return $this->image->cacheFile($this->format, $this->quality);
    }

    /**
     * Filter image by using user defined filter parameters.
     *
     * @param string $filter Filter to be used.
     */
    public function filter($filter = 'image.filters.default')
    {
        $filters = (array) $this->get($filter, []);
        foreach ($filters as $params) {
            $params = (array) $params;
            $method = array_shift($params);
            $this->__call($method, $params);
        }
    }

    /**
     * Return the image higher quality version
     *
     * @return ImageMedium the alternative version with higher quality
     */
    public function higherQualityAlternative()
    {
        if ($this->alternatives) {
            $max = reset($this->alternatives);
            foreach($this->alternatives as $alternative)
            {
                if($alternative->quality() > $max->quality())
                {
                    $max = $alternative;
                }
            }

            return $max;
        } else {
            return $this;
        }
    }

}
