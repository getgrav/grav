<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Data\Blueprint;

class ImageMedium extends Medium
{
    /**
     * @var array
     */
    protected $thumbnailTypes = [ 'page', 'media', 'default' ];

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
        'smooth', 'sharp', 'edge', 'colorize', 'sepia', 'enableProgressive'
    ];

    /**
     * @var array
     */
    public static $magic_resize_actions = [
        'resize' => [ 0, 1 ],
        'forceResize' => [ 0, 1 ],
        'cropResize' => [ 0, 1 ],
        'crop' => [ 0, 1, 2, 3 ],
        'cropResize' => [ 0, 1 ],
        'zoomCrop' => [ 0, 1 ]
    ];

    /**
     * @var array
     */
    protected $derivatives = [];

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

        $config = self::$grav['config'];

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
        $output = preg_replace('|^' . GRAV_ROOT . '|', '', $this->saveImage());

        if ($reset) {
            $this->reset();
        }

        return self::$grav['base_url'] . $output . $this->querystring() . $this->urlHash();
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
        if (empty($this->alternatives) && empty($this->derivatives)) {
            if ($reset) {
                $this->reset();
            }
            return '';
        }

        if (!empty($this->derivatives)) {
          asort($this->derivatives);

          foreach ($this->derivatives as $url => $width) {
              $srcset[] = $url . ' ' . $width . 'w';
          }

          $srcset[] = $this->url($reset) . ' ' . $this->get('width') . 'w';
        }
        else {
          $srcset = [ $this->url($reset) . ' ' . $this->get('width') . 'w' ];
          foreach ($this->alternatives as $ratio => $medium) {
              $srcset[] = $medium->url($reset) . ' ' . $medium->get('width') . 'w';
          }
        }

        return implode(', ', $srcset);
    }

    /**
     * Generate derivatives
     *
     * @param  int $min_width
     * @param  int $max_width
     * @param  int $step
     * @return $this
     */
    public function derivatives($min_width, $max_width, $step = 200) {
      $width = $min_width;

      // Do not upscale images.
      if ($max_width > $this->get('width')) {
        $max_width = $this->get('width');
      }

      while ($width <= $max_width) {
        $ratio = $width / $this->get('width');
        $derivative = MediumFactory::scaledFromMedium($this, 1, $ratio);
        if (is_array($derivative)) {
          $this->addDerivative($derivative['file']);
        }
        $width += $step;
      }
      return $this;
    }

    /**
     * Add a derivative
     *
     * @param  ImageMedium $image
     */
    public function addDerivative(ImageMedium $image) {
      $this->derivatives[$image->url()] = $image->get('width');
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
            $this->filter();
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
     * Sets the quality of the image
     *
     * @param  int $quality 0-100 quality
     * @return Medium
     */
    public function quality($quality)
    {
        if (!$this->image) {
            $this->image();
        }

        $this->quality = $quality;
        return $this;
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

            foreach ($this->alternatives as $ratio => $medium) {
                $args_copy = $args;

                // regular image: resize 400x400 -> 200x200
                // --> @2x: resize 800x800->400x400
                if (isset(self::$magic_resize_actions[$method])) {
                    foreach (self::$magic_resize_actions[$method] as $param) {
                        if (isset($args_copy[$param])) {
                            $args_copy[$param] = (int) $args_copy[$param] * $ratio;
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
        $locator = self::$grav['locator'];

        $file = $this->get('filepath');
        $cacheDir = $locator->findResource('cache://images', true);

        $this->image = ImageFile::open($file)
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir)
            ->setPrettyName(basename($this->get('basename')));

        $this->filter();

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

        if (isset($this->result)) {
            return $this->result;
        }

        if ($this->get('debug') && !$this->debug_watermarked) {
            $ratio = $this->get('ratio');
            if (!$ratio) {
                $ratio = 1;
            }

            $locator = self::$grav['locator'];
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
}
