<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Gregwar\Image\Image as ImageFile;

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
    protected $quality = 85;
    
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
        'smooth', 'sharp', 'edge', 'colorize', 'sepia'
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
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = array(), Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        $image_info = getimagesize($this->get('filepath'));
        $this->def('width', $image_info[0]);
        $this->def('height', $image_info[1]);
        $this->def('mime', $image_info['mime']);
        $this->def('debug', self::$grav['config']->get('system.images.debug'));

        $this->set('thumbnails.media', $this->get('filepath'));

        $this->reset();
    }

    /**
     * Add meta file for the medium.
     *
     * @param $format
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
     * @return  string path to image
     */
    public function path($reset = true)
    {
        $output = $this->saveImage();

        if ($reset) $this->reset();
        
        return GRAV_ROOT . '/' . $output;
    }

    /**
     * Return URL to image.
     *
     * @param bool $reset
     * @return string
     */
    public function url($reset = true)
    {
        $output = '/' . $this->saveImage();

        if ($reset) {
            $this->reset();
        }

        return self::$grav['base_url'] . $output;
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

        $srcset = [ $this->url($reset) . ' ' . $this->get('width') . 'w' ];

        foreach ($this->alternatives as $ratio => $medium) {
            $srcset[] = $medium->url($reset) . ' ' . $medium->get('width') . 'w';
        }

        return implode(', ', $srcset);
    }

    /**
     * Called from Parsedown (ParsedownGravTrait::inlineImage calls this method on the Medium)
     */
    public function sourceParsedownElement($attributes, $reset = true)
    {
        empty($attributes['src']) && $attributes['src'] = $this->url(false);
        
        $srcset = $this->srcset($reset);
        if ($srcset) {
            empty($attributes['srcset']) && $attributes['srcset'] = $srcset;
            empty($attributes['sizes']) && $attributes['sizes'] = '100vw';
        }

        return [ 'name' => 'image', 'attributes' => $attributes ];
    }

    /**
     * Reset image.
     *
     * @return $this
     */
    public function reset()
    {
        $this->image = null;

        $this->image();
        $this->filter();

        $this->format = 'guess';
        $this->quality = 80;
        $this->debug_watermarked = false;

        return parent::reset();
    }

    /**
     * Enable link for the medium object.
     *
     * @param null $width
     * @param null $height
     * @return $this
     */
    public function link($reset = true)
    {
        if ($this->mode !== 'source') {
            $this->display('source');
        }

        $this->linkAttributes['href'] = $this->url(false);
        $srcset = $this->srcset($reset);

        if ($srcset) {
            $this->linkAttributes['data-srcset'] = $srcset;
        }

        $this->thumbnail('auto');
        $thumb = $this->display('thumbnail');
        $thumb->linked = true;

        return $thumb;
    }

    /**
     * Enable lightbox for the medium.
     *
     * @param null $width
     * @param null $height
     * @return Medium
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
     * @param  Int $quality 0-100 quality
     * @return Medium
     */
    public function quality($quality)
    {
        $this->quality = $quality;
        return $this;
    }

    /**
     * Sets image output format.
     *
     * @param string $format
     * @param int $quality
     * @return $this
     */
    public function format($format)
    {
        $this->format = $format;
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
            return $this;
        }

        // Always initialize image.
        if (!$this->image) {
            $this->image();
        }

        try {
            $result = call_user_func_array(array($this->image, $method), $args);

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

                call_user_func_array(array($medium, $method), $args_copy);
            }
        } catch (\BadFunctionCallException $e) { }

        return $this;
    }

    /**
     * Gets medium image, resets image manipulation operations.
     *
     * @param string $variable
     * @return $this
     */
    protected function image()
    {
        $locator = self::$grav['locator'];

        // TODO: add default file
        $file = $this->get('filepath');
        $this->image = ImageFile::open($file)
            ->setCacheDir($locator->findResource('cache://images', false))
            ->setActualCacheDir($locator->findResource('cache://images', true))
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
            $this->image();
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
        $filters = (array) $this->get($filter, array());
        foreach ($filters as $params) {
            $params = (array) $params;
            $method = array_shift($params);
            $this->__call($method, $params);
        }
    }
}
