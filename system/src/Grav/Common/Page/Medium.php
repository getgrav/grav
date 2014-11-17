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
    protected $quality = 85;

    public static $valid_actions = ['resize', 'forceResize', 'cropResize', 'crop', 'cropZoom',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss', 'smooth', 'sharp', 'edge', 'colorize', 'sepia' ];

    /**
     * @var array
     */
    protected $meta = array();

    /**
     * @var string
     */
    protected $linkTarget;

    /**
     * @var string
     */
    protected $linkAttributes;

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
        /** @var Config $config */
        $config = self::$grav['config'];

        if ($this->image) {
            $output = $this->image->cacheFile($this->type, $this->quality);
            $this->reset();
            $output = ROOT_DIR . $output;
        } else {
            $output = $this->get('path') . '/' . $this->get('filename');
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
     * Return URL to file.
     *
     * @return string
     */
    public function url()
    {
        if ($this->image) {
            $output = $this->image->cacheFile($this->type, $this->quality);
            $this->reset();
        } else {
            $relPath = preg_replace('|^' . ROOT_DIR . '|', '', $this->get('path'));
            $output = $relPath . '/' . $this->get('filename');
        }

        return self::$grav['base_url'] . '/'. $output;
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
        if (!$this->image) {
            $this->image();
        }

        $this->type = $type;
        $this->quality = $quality;
        return $this;
    }

    /**
     * Returns <img> tag from the medium.
     *
     * @param string $title
     * @param string $class
     * @param string $type
     * @param int $quality
     * @return string
     */
    public function img($title = null, $class = null, $type = null, $quality = 80)
    {
        if (!$this->image) {
            $this->image();
        }

        $output = $this->html($title, $class, $type, $quality);

        return $output;
    }

    /**
     * Return HTML markup from the medium.
     *
     * @param string $title
     * @param string $class
     * @param string $type
     * @param int $quality
     * @return string
     */
    public function html($title = null, $class = null, $type = null, $quality = 80)
    {
        $title = $title ? $title : $this->get('title');
        $class = $class ? $class : '';

        if ($this->image) {
            $type = $type ? $type : $this->type;
            $quality = $quality ? $quality : $this->quality;

            $url = $this->url($type, $quality);
            $this->reset();

            $output = '<img src="' . $url . '" class="'. $class . '" alt="' . $title . '" />';
        } else {
            $output = $title;
        }

        if ($this->linkTarget) {
            /** @var Config $config */
            $config = self::$grav['config'];

            $output = '<a href="' . self::$grav['base_url'] . '/'. $this->linkTarget
                . '"' . $this->linkAttributes. ' class="'. $class . '">' . $output . '</a>';

            $this->linkTarget = $this->linkAttributes = null;
        }

        return $output;
    }

    /**
     * Return lightbox HTML for the medium.
     *
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function lightbox($width = null, $height = null)
    {
        $this->linkAttributes = ' rel="lightbox"';

        return $this->link($width, $height);
    }

    public function lightboxRaw($width = null, $height = null)
    {
        /** @var Config $config */
        $config = self::$grav['config'];
        $url = $this->url();
        $this->link($width, $height);
        $lightbox_url = self::$grav['base_url'] . '/'. $this->linkTarget;

        return array('a_url' => $lightbox_url, 'a_rel' => 'lightbox', 'img_url' => $url);
    }

    /**
     * Return link HTML for the medium.
     *
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function link($width = null, $height = null)
    {
        if ($this->image) {
            $image = clone $this->image;
            if ($width && $height) {
                $image->cropResize($width, $height);
            }
            $this->linkTarget = $image->cacheFile($this->type, $this->quality);
        } else {
            // TODO: we need to find out URI in a bit better way.
            $relPath = preg_replace('|^' . ROOT_DIR . '|', '', $this->get('path'));
            $this->linkTarget = $relPath. '/' . $this->get('filename');
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

        if ($this->get('type') == 'image') {
            $this->image();
            $this->filter();
        }
        $this->type = 'guess';
        $this->quality = 80;

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
        $result = call_user_func_array(array($this->image, $method), $args);

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
        // TODO: add default file
        $file = $this->get($variable);
        $this->image = ImageFile::open($file)
            ->setCacheDir(basename(IMAGES_DIR))
            ->setActualCacheDir(IMAGES_DIR)
            ->setPrettyName(basename($this->get('basename')));

        $this->filter();

        return $this;
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
