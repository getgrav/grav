<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Gregwar\Image\Image as ImageFile;

/**
 * The Image medium holds information related to an individual image. These are then stored in the Media object.
 *
 * @author RocketTheme
 * @license MIT
 *
 * @property string $file_name
 * @property string $type
 * @property string $name       Alias of file_name
 * @property string $description
 * @property string $url
 * @property string $path
 * @property string $thumb
 * @property int    $width
 * @property int    $height
 * @property string $mime
 * @property int    $modified
 *
 * Medium can have up to 3 files:
 * - video.mov              Medium file itself.
 * - video.mov.meta.yaml    Metadata for the medium.
 * - video.mov.thumb.jpg    Thumbnail image for the medium.
 *
 */
class ImageMedium extends Medium
{
    /**
     * @var ImageFile
     */
    protected $image;

    protected $type = 'guess';
    
    protected $quality = 85;
    
    protected $debug_watermarked = false;

    public static $valid_actions = [
        // Medium functions
        'format', 'lightbox', 'link', 'reset',

        // Gregwar Image functions
        'resize', 'forceResize', 'cropResize', 'crop', 'cropZoom',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss', 'smooth', 'sharp', 'edge', 'colorize', 'sepia' ];

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
     * Return HTML markup from the medium.
     *
     * @param string $title
     * @param string $class
     * @param bool $reset
     * @return string
     */
    public function html($title = null, $class = null, $reset = true)
    {
        $data = $this->htmlRaw($reset);

        $title = $title ? $title : $this->get('title');
        $class = $class ? $class : '';

        $attributes = $data['img_srcset'] ? ' srcset="' . $data['img_srcset'] . '" sizes="100vw"' : '';
        $output = '<img src="' . $data['img_src'] . '"' . $attributes . ' class="'. $class . '" alt="' . $title . '" />';

        if (isset($data['a_href'])) {
            $attributes = '';
            foreach ($data['a_attributes'] as $prop => $value) {
                $attributes .= " {$prop}=\"{$value}\"";
            }

            $output = '<a href="' . $data['a_href'] . '"' . $attributes . ' class="'. $class . '">' . $output . '</a>';
        }

        return $output;
    }

    /**
     * Return HTML array from medium.
     *
     * @param bool   $reset
     * @param string $title
     *
     * @return array
     */
    public function htmlRaw($reset = true, $title = '')
    {
        $output = [];

        $output['img_src'] = $this->url(false);
        $output['img_srcset'] = $this->srcset($reset);

        if ($this->linkTarget) {
            $output['a_href'] = $this->linkTarget;
            $output['a_attributes'] = $this->linkAttributes;

            $this->linkTarget = null;
            $this->linkAttributes = [];
        }

        return $output;
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
     * Enable link for the medium object.
     *
     * @param null $width
     * @param null $height
     * @return $this
     */
    public function link($width = null, $height = null)
    {
        if ($width && $height) {
            $this->cropResize($width, $height);
        }

        $this->linkTarget = $this->url(false);
        $srcset = $this->srcset();

        if ($srcset) {
            $this->linkAttributes['data-srcset'] = $srcset;
        }

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

        // Always initialize image.
        if (!$this->image) {
            $this->image();
        }

        try {
            $result = call_user_func_array(array($this->image, $method), $args);

            foreach ($this->alternatives as $ratio => $medium) {
                $args_copy = $args;

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
