<?php
namespace Grav\Common\Page;

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
class Medium extends Data
{
    use GravTrait;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var ImageFile
     */
    protected $image;

    protected $type = 'guess';
    protected $quality = DEFAULT_IMG_QUALITY;
    protected $debug_watermarked = false;

    public static $valid_actions = [
        // Medium functions
        'format', 'lightbox', 'link', 'reset',

        // Gregwar Image functions
        'resize', 'forceResize', 'cropResize', 'crop', 'cropZoom', 'quality',
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
     * @var array
     */
    protected $meta = array();

    /**
     * @var array
     */
    protected $alternatives = array();

    /**
     * @var string
     */
    protected $linkTarget;

        /**
     * @var string
     */
    protected $linkSrcset;

    /**
     * @var string
     */
    protected $linkAttributes = [];

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = array(), Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        $file_path = $this->get('path') . '/' . $this->get('filename');
        $file_parts = pathinfo($file_path);

        $this->set('thumb', $file_path);
        $this->set('extension', $file_parts['extension']);
        $this->set('filename', $this->get('filename'));

        if ($this->get('type') == 'image') {
            $image_info = getimagesize($file_path);
            $this->def('width', $image_info[0]);
            $this->def('height', $image_info[1]);
            $this->def('mime', $image_info['mime']);
            $this->reset();
        } else {
            $this->def('mime', 'application/octet-stream');
        }

        $this->set('debug', self::getGrav()['config']->get('system.images.debug'));
    }

    /**
     * Return string representation of the object (html or url).
     *
     * @return string
     */
    public function __toString()
    {
        return $this->linkImage ? $this->html() : $this->url();
    }

    /**
     * Return PATH to file.
     *
     * @return  string path to file
     */
    public function path()
    {
        if ($this->image) {
            $output = $this->saveImage();
            $this->reset();
            $output = GRAV_ROOT . '/' . $output;
        } else {
            $output = $this->get('path') . '/' . $this->get('filename');
        }
        return $output;
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     */
    public function url($reset = true)
    {
        if ($this->image) {
            $output = '/' . $this->saveImage();

            if ($reset) {
                $this->reset();
            }
        } else {
            $output = preg_replace('|^' . GRAV_ROOT . '|', '', $this->get('path')) . '/' . $this->get('filename');
        }

        return self::getGrav()['base_url'] . $output;
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
     * Returns <img> tag from the medium.
     *
     * @param string $title
     * @param string $class
     * @param string $type
     * @param int $quality
     * @param bool $reset
     * @return string
     */
    public function img($title = null, $class = null, $type = null, $quality = DEFAULT_IMG_QUALITY, $reset = true)
    {
        if (!$this->image) {
            $this->image();
        }

        $output = $this->html($title, $class, $type, $quality, $reset);

        return $output;
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

        if ($this->image) {
            $attributes = $data['img_srcset'] ? ' srcset="' . $data['img_srcset'] . '" sizes="100vw"' : '';
            $output = '<img src="' . $data['img_src'] . '"' . $attributes . ' class="'. $class . '" alt="' . $title . '" />';
        } else {
            $output = $data['text'];
        }

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

        if ($this->image) {
            $output['img_src'] = $this->url(false);
            $output['img_srcset'] = $this->srcset($reset);
        } else {
            $output['text'] = $title;
        }

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
    public function format($type = null, $quality = DEFAULT_IMG_QUALITY)
    {
        if (!$this->image) {
            $this->image();
        }

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
        if ($this->image) {
            if ($width && $height) {
                $this->cropResize($width, $height);
            }

            $this->linkTarget = $this->url(false);
            $srcset = $this->srcset();

            if ($srcset) {
                $this->linkAttributes['data-srcset'] = $srcset;
            }
        } else {
            // TODO: we need to find out URI in a bit better way.
            $this->linkTarget = self::getGrav()['base_url'] . preg_replace('|^' . GRAV_ROOT . '|', '', $this->get('path')) . '/' . $this->get('filename');
        }

        return $this;
    }

    /**
     * Enable lightbox for the medium.
     *
     * @param null $width
     * @param null $height
     * @return Medium
     */
    public function lightbox($width = null, $height = null)
    {
        $this->linkAttributes['rel'] = 'lightbox';

        return $this->link($width, $height);
    }

    /**
     * Reset image.
     *
     * @return $this
     */
    public function reset()
    {
        $this->image = null;

        if ($this->get('type') == 'image') {
            $this->image();
            $this->filter();
        }
        $this->type = 'guess';
        $this->quality = DEFAULT_IMG_QUALITY;
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
    public function image($variable = 'thumb')
    {
        $locator = self::getGrav()['locator'];

        // TODO: add default file
        $file = $this->get($variable);
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

            $locator = self::getGrav()['locator'];
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
    public function addMetaFile($type)
    {
        $this->meta[$type] = $type;

        $path = $this->get('path') . '/' . $this->get('filename') . '.meta.' . $type;
        if ($type == 'yaml') {
            $this->merge(CompiledYamlFile::instance($path)->content());
        } elseif (in_array($type, array('jpg', 'jpeg', 'png', 'gif'))) {
            $this->set('thumb', $path);
        }
        $this->reset();

        return $this;
    }

    /**
     * Add alternative Medium to this Medium.
     *
     * @param $ratio
     * @param Medium $alternative
     */
    public function addAlternative($ratio, Medium $alternative)
    {
        if (!is_numeric($ratio) || $ratio === 0) {
            return;
        }

        $alternative->set('ratio', $ratio);

        $this->alternatives[(float) $ratio] = $alternative;
    }

    public function getAlternatives()
    {
        return $this->alternatives;
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
