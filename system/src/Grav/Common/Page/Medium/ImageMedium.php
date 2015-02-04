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
     * @var ImageFile
     */
    protected $image;

    protected $type = 'guess';
    
    protected $quality = 85;
    
    protected $debug_watermarked = false;

    public static $magic_actions = [
        'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss',
        'smooth', 'sharp', 'edge', 'colorize', 'sepia'
    ];

    public static $size_param_actions = [
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

        $this->reset();
    }

    /**
     * Return PATH to file.
     *
     * @return  string path to file
     */
    public function path($reset = true)
    {
        $output = $this->saveImage();

        if ($reset) $this->reset();
        
        return GRAV_ROOT . '/' . $output;
    }

    /**
     * Return URL to file.
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
    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true)
    {
        $outer_attributes = [];
        $image_attributes = [
            'src' => $this->url(false),
        ];
        
        $srcset = $this->srcset($reset);
        if ($srcset) {
            $image_attributes['srcset'] = $srcset;
            $image_attributes['sizes'] = '100vw';
        }

        if ($title) {
            $image_attributes['title'] = $title;
            $outer_attributes['title'] = $title;
        }

        if ($alt) {
            $image_attributes['alt'] = $alt;
        }

        if ($class) {
            $image_attributes['class'] = $class;
            $outer_attributes['class'] = $class;
        }

        $element = [ 'name' => 'image', 'attributes' => $image_attributes ];
        
        if ($this->linkAttributes) {
            $element = [
                'name' => 'a',
                'handler' => 'element',
                'text' => $element,
                'attributes' => $this->linkAttributes
            ];

            $this->linkAttributes = [];
        }

        $element['attributes'] = array_merge($outer_attributes, $element['attributes']);

        return $element;
    }

    /**
     * Enable link for the medium object.
     *
     * @param null $width
     * @param null $height
     * @return $this
     */
    public function link($width = null, $height = null, $reset = true)
    {
        if ($width && $height) {
            $this->cropResize($width, $height);
        }

        $this->linkAttributes['href'] = $this->url(false);
        $srcset = $this->srcset($reset);

        if ($srcset) {
            $this->linkAttributes['data-srcset'] = $srcset;
        }

        return $this;
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
     * @param string $type
     * @param int $quality
     * @return $this
     */
    public function format($type = null, $quality = 80)
    {
        $this->type = $type;
        $this->quality = $quality;
        return $this;
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

        $this->type = 'guess';
        $this->quality = 80;
        $this->debug_watermarked = false;

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
                if (isset(self::$size_param_actions[$method])) {
                    foreach (self::$size_param_actions[$method] as $param) {
                        if (isset($args_copy[$param])) {
                            $args_copy[$param] = (int) $args_copy[$param] * $ratio;
                        }
                    }
                }

                call_user_func_array(array($medium, $method), $args_copy);
            }
        } catch (\BadFunctionCallException $e) {
            $result = null;
        }

        // Returns either current object or result of the action.
        return $result instanceof ImageFile ? $this : $result;
    }

    /**
     * Gets medium image, resets image manipulation operations.
     *
     * @param string $variable
     * @return $this
     */
    public function image()
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

        return $this->image->cacheFile($this->type, $this->quality);
    }

    /**
     * Add meta file for the medium.
     *
     * @param $type
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
