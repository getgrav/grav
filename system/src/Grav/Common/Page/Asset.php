<?php
namespace Grav\Common\Page;

use Grav\Common\Data\Blueprint;
use Grav\Common\Uri;
use Grav\Common\Data\Data;
use Grav\Common\Filesystem\File\Yaml;
use Grav\Common\Registry;
use Gregwar\Image\Image as ImageFile;

/**
 * The Image asset holds information related to an individual image. These are then stored in the Assets object.
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
 * Asset can have up to 3 files:
 * - video.mov              Asset file itself.
 * - video.mov.meta.yaml    Metadata for the asset.
 * - video.mov.thumb.jpg    Thumbnail image for the asset.
 *
 */
class Asset extends Data
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var ImageFile
     */
    protected $image;

    protected $type = 'guess';
    protected $quality = 80;

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

        if ($this->get('type') == 'image') {
            $filePath = $this->get('path') . '/' . $this->get('filename');
            $image_info = getimagesize($filePath);
            $this->set('thumb', $filePath);
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
     * Return URL to file.
     *
     * @return string
     */
    public function url()
    {
        $config = Registry::get('Config');

        if ($this->image) {
            $output = $this->image->cacheFile($this->type, $this->quality);
            $this->reset();
        } else {
            $relPath = preg_replace('|^' . ROOT_DIR . '|', '', $this->get('path'));
            $output = $relPath . '/' . $this->get('filename');
        }

        return $config->get('system.base_url_relative') . '/'. $output;
    }

    /**
     * Sets image output format.
     *
     * @param string $type
     * @param int $quality
     */
    public function format($type = null, $quality = 80)
    {
        if (!$this->image) {
            $this->image();
        }

        $this->type = $type;
        $this->quality = $quality;
    }

    /**
     * Returns <img> tag from the asset.
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
     * Return HTML markup from the asset.
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
            $config = Registry::get('Config');

            $output = '<a href="' . $config->get('system.base_url_relative') . '/'. $this->linkTarget
                . '"' . $this->linkAttributes. ' class="'. $class . '">' . $output . '</a>';

            $this->linkTarget = $this->linkAttributes = null;
        }

        return $output;
    }

    /**
     * Return lightbox HTML for the asset.
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

    /**
     * Return link HTML for the asset.
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
     * Gets asset image, resets image manipulation operations.
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
     * Add meta file for the asset.
     *
     * @param $type
     * @return $this
     */
    public function addMetaFile($type)
    {
        $this->meta[$type] = $type;

        $path = $this->get('path') . '/' . $this->get('filename') . '.meta.' . $type;
        if ($type == 'yaml') {
            $this->merge(Yaml::instance($path)->content());
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
